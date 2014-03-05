<?php

namespace Phosphorum\Controllers;

use Phosphorum\Models\Posts,
	Phosphorum\Models\PostsViews,
	Phosphorum\Models\PostsReplies,
	Phosphorum\Models\PostsBounties,
	Phosphorum\Models\PostsHistory,
	Phosphorum\Models\PostsVotes,
	Phosphorum\Models\Categories,
	Phosphorum\Models\Activities,
	Phosphorum\Models\IrcLog,
	Phosphorum\Models\Users,
	Phalcon\Http\Response;

class DiscussionsController extends \Phalcon\Mvc\Controller
{

	/**
	 * This initializes the timezone in each request
	 */
	public function initialize()
	{
		$timezone = $this->session->get('identity-timezone');
		if ($timezone) {
			date_default_timezone_set($timezone);
		}
	}

	/**
	 * This method prepares the queries to be executed in each list of posts
	 * The returned builders are used as base in the search, tagged list and index lists
	 */
	protected function prepareQueries($joinReply=false)
	{

		$itemBuilder = $this->modelsManager->createBuilder()
			->from(array(
				'p' => 'Phosphorum\Models\Posts'
			))
			->orderBy('p.sticked DESC, p.created_at DESC');

        if ($joinReply) {
            $itemBuilder->groupBy("p.id")
                        ->join('Phosphorum\Models\PostsReplies', "r.posts_id = p.id", 'r');
        }

		$totalBuilder = clone $itemBuilder;

		$itemBuilder->columns(array(
			'p.*'
		))
		->limit(40);

		$totalBuilder->columns('COUNT(*) AS count');

		/**
		 * Query the categories ordering them by number_posts
		 */
		if (!$this->view->getCache()->exists('sidebar')) {
			$this->view->categories = Categories::find(array(
				'order' => 'number_posts DESC, name'
			));
		}

		return array($itemBuilder, $totalBuilder);
	}

	/**
	 * Shows latest posts using an order clause
	 */
	public function indexAction($order=null, $offset=0)
	{

        if ($order == "answers") {
            list($itemBuilder, $totalBuilder) = $this->prepareQueries(true);
        } else {
            list($itemBuilder, $totalBuilder) = $this->prepareQueries();
        }

		/**
		 * Create the conditions according to the parameter order
		 */
		$params = null;
		switch ($order) {

			case 'hot':
				$this->tag->setTitle('Hot Discussions');
				$userId = $this->session->get('identity');
				$itemBuilder->orderBy('p.modified_at DESC');
				$totalBuilder->orderBy('p.modified_at DESC');
				break;

			case 'my':
				$this->tag->setTitle('My Discussions');
				$userId = $this->session->get('identity');
				if ($userId) {
					$params = array($userId);
					$itemBuilder->where('p.users_id = ?0');
					$totalBuilder->where('p.users_id = ?0');
				}
				break;

			case 'unanswered':
				$this->tag->setTitle('Unanswered Discussions');
				$itemBuilder->where('p.number_replies = 0 AND p.accepted_answer <> "Y"');
				$totalBuilder->where('p.number_replies = 0 AND p.accepted_answer <> "Y"');
				break;

            case 'answers':
				$this->tag->setTitle('My Answers');
				$userId = $this->session->get('identity');
				if ($userId) {
					$params = array($userId);
					$itemBuilder->where('r.users_id = ?0');
					$totalBuilder->where('r.users_id = ?0');
				}
				break;

			default:
				$this->tag->setTitle('Discussions');
		}

		$itemBuilder->andWhere('p.deleted = 0');
		$totalBuilder->andWhere('p.deleted = 0');

		$itemBuilder->offset((int) $offset);

		$this->view->posts = $itemBuilder
			->getQuery()
			->execute($params);

		$this->view->totalPosts = $totalBuilder
			->getQuery()
			->setUniqueRow(true)
			->execute($params);

		if (!$order) {
			$order = 'new';
		}

		$this->view->currentOrder = $order;
		$this->view->offset = $offset;
		$this->view->paginatorUri = 'discussions/' . $order;
		$this->view->canonical = '';
	}

	/**
	 * Shows latest posts by category
	 */
	public function categoryAction($categoryId, $slug, $offset=0)
	{
		$this->tag->setTitle('Discussions');

		$category = Categories::findFirstById($categoryId);
		if (!$category) {
			$this->flashSession->notice('The category doesn\'t exist');
			return $this->response->redirect();
		}

		list($itemBuilder, $totalBuilder) = $this->prepareQueries();

		$totalBuilder->where('p.categories_id = ?0 AND deleted = 0');

		$posts = $itemBuilder
			->where('p.categories_id = ?0 AND deleted = 0')
			->orderBy('p.created_at DESC')
			->offset($offset)
			->getQuery()
			->execute(array($categoryId));

		if (!count($posts)) {
			$this->flashSession->notice('There are no posts in category: '.$category->name);
			return $this->response->redirect();
		}

		$totalPosts = $totalBuilder
			->getQuery()
			->setUniqueRow(true)
			->execute(array($categoryId));

		$this->view->posts = $posts;
		$this->view->totalPosts = $totalPosts;
		$this->view->currentOrder = null;
		$this->view->offset = $offset;
		$this->view->paginatorUri = 'category/' . $category->id . '/' . $category->slug;
	}

	/**
	 * This shows the create post form and also store the related post
	 */
	public function createAction()
	{

		$usersId = $this->session->get('identity');
		if (!$usersId) {
			$this->flashSession->error('You must be logged first');
			return $this->response->redirect();
		}


		$this->tag->setTitle('Start a Discussion');

		if ($this->request->isPost()) {

			$title = $this->request->getPost('title', 'trim');

			$user = Users::findFirstById($usersId);
			$user->karma += 10;
			$user->votes_points += 10;
			$user->save();

			$post = new Posts();
			$post->users_id = $usersId;
			$post->categories_id = $this->request->getPost('categoryId');
			$post->title = $title;
			$post->slug = $this->tag->friendlyTitle($title);
			$post->content = $this->request->getPost('content');

			if ($post->save()) {
				return $this->response->redirect('forum/discussion/' . $post->id . '/' . $post->slug);
			}

			foreach ($post->getMessages() as $message) {
				$this->flash->error($message);
			}
		}


		$this->view->categories = Categories::find(array(
			'order' => 'name'
		));

	}

	/**
	 * This shows the create post form and also store the related post
	 */
	public function editAction($id)
	{

		$usersId = $this->session->get('identity');
		if (!$usersId) {
			$this->flashSession->error('You must be logged first');
			return $this->response->redirect();
		}

		/**
		 * Find the post using get
		 */
		$post = Posts::findFirst(array(
			"id = ?0 AND (users_id = ?1 OR 'Y' = ?2)",
			"bind" => array($id, $usersId, $this->session->get('identity-moderator'))
		));
		if (!$post) {
			$this->flashSession->error('The discussion does not exist');
			return $this->response->redirect();
		}

		if ($this->request->isPost()) {

			$title = $this->request->getPost('title', 'trim');
			$content = $this->request->getPost('content');

			$post->categories_id = $this->request->getPost('categoryId');
			$post->title = $title;
			$post->slug = $this->tag->friendlyTitle($title);
			$post->content = $content;
			$post->edited_at = time();

			$usersId = $this->session->get('identity');
			if ($post->users_id != $usersId) {
				$user = Users::findFirstById($usersId);
				if ($user) {
					$user->karma += 25;
					$user->votes_points += 25;
					$user->save();
				}
			}

			if ($post->save()) {
				return $this->response->redirect('forum/discussion/' . $post->id . '/' . $post->slug);
			}

			foreach ($post->getMessages() as $message) {
				$this->flash->error($message);
			}

		} else {

			$this->tag->displayTo('id', $post->id);
			$this->tag->displayTo('title', $post->title);
			$this->tag->displayTo('content', $post->content);
			$this->tag->displayTo('categoryId', $post->categories_id);
		}

		$this->tag->setTitle('Edit Discussion: ' . $this->escaper->escapeHtml($post->title));

		$this->view->categories = Categories::find(array(
			'order' => 'name'
		));

		$this->view->post = $post;
	}

	/**
	 * This shows the create post form and also store the related post
	 */
	public function deleteAction($id)
	{

		$usersId = $this->session->get('identity');
		if (!$usersId) {
			$this->flashSession->error('You must be logged first');
			return $this->response->redirect();
		}

		/**
		 * Find the post using get
		 */
		$post = Posts::findFirst(array(
			"id = ?0 AND (users_id = ?1 OR 'Y' = ?2)",
			"bind" => array($id, $usersId, $this->session->get('identity-moderator'))
		));
		if (!$post) {
			$this->flashSession->error('The discussion does not exist');
			return $this->response->redirect();
		}

		if ($post->sticked == 'Y') {
			$this->flashSession->error('The discussion cannot be deleted because it\'s sticked');
			return $this->response->redirect();
		}

		$post->deleted = 1;
		if ($post->save()) {

			$usersId = $this->session->get('identity');
			if ($post->users_id != $usersId) {

				$user = Users::findFirstById($usersId);
				if ($user) {
					if ($user->moderator == 'Y') {
						$user->karma += 10;
						$user->votes_points += 10;
						$user->save();
					}
				}

				$user = $post->user;
				$user->karma -= 10;
				$user->votes_points -= 10;
				$user->save();
			}

			$this->flashSession->success('Discussion was successfully deleted');
			return $this->response->redirect();
		}

	}

	/**
	 * Displays a post and its comments
	 *
	 * @param int $id
	 * @param string $slug
	 */
	public function viewAction($id, $slug)
	{
		$id = (int) $id;

		if (!$this->request->isPost()) {

			$usersId = $this->session->get('identity');
			if (!$usersId) {

				/**
			 	 * Enable cache
			 	 */
				$this->view->cache(array('key' => 'post-' . $id));

				/**
				 * Check for a cache
				 */
				if ($this->viewCache->exists('post-' . $id)) {
					return;
				}
			}

			/**
			 * Find the post using get
			 */
			$post = Posts::findFirstById($id);
			if (!$post) {
				$this->flashSession->error('The discussion does not exist');
				return $this->response->redirect();
			}

			if ($post->deleted) {
				$this->flashSession->error('The discussion is deleted');
				return $this->response->redirect();
			}

			$ipAddress = $this->request->getClientAddress();

			$viewed = PostsViews::count(array(
				'posts_id = ?0 AND ipaddress = ?1',
				'bind' => array($id, $ipAddress)
			));

			/**
			 * A view is stored by ipaddress
			 */
			if (!$viewed) {

				/**
				 * Increase the number of views in the post
				 */
				$post->number_views++;
				if ($post->users_id != $usersId) {

					$post->user->karma += 1;
					$post->user->votes_points += 1;

					$user = Users::findFirstById($usersId);
					if ($user) {
						$user->karma += 2;
						$user->votes_points += 2;
						$user->save();
					}
				}

				$postView = new PostsViews();
				$postView->post = $post;
				$postView->ipaddress = $ipAddress;
				if (!$postView->save()) {
					foreach ($postView->getMessages() as $message) {
						$this->flash->error($message);
					}
				}
			}

			/**
			 * Generate cannonical meta
			 */
			$this->view->canonical = 'discussion/' . $post->id . '/' . $post->slug;

		} else {

			/**
			 * Find the post using POST
			 */
			$post = Posts::findFirstById($this->request->getPost('id'));
			if (!$post) {
				$this->flashSession->error('The discussion does not exist');
				return $this->response->redirect();
			}

			if ($post->deleted) {
				$this->flashSession->error('The discussion is deleted');
				return $this->response->redirect();
			}

			$content = $this->request->getPost('content', 'trim');
			if ($content) {

				$usersId = $this->session->get('identity');

				/**
				 * Check if the question can have a bounty before add the reply
				 */
				$canHaveBounty = $post->canHaveBounty();

				/**
				 * Only update the number of replies if the user that commented isn't the same that posted
				 */
				if ($post->users_id != $usersId) {

					$post->number_replies++;
					$post->modified_at = time();

					$post->user->karma += 5;
					$post->user->votes_points += 10;

					$user = Users::findFirstById($usersId);
					$user->karma += 10;
					$user->votes_points += 10;
					$user->save();
				}

				$postReply = new PostsReplies();
				$postReply->post = $post;
				$postReply->users_id = $usersId;
				$postReply->content = $content;

				if ($postReply->save()) {

					if ($post->users_id != $usersId && $canHaveBounty) {
						$bounty = $post->getBounty();
						$postBounty = new PostsBounties();
						$postBounty->posts_id = $post->id;
						$postBounty->users_id = $usersId;
						$postBounty->posts_replies_id = $postReply->id;
						$postBounty->points = $bounty['value'];
						if (!$postBounty->save()) {
							foreach ($postBounty->getMessages() as $message) {
								$this->flash->error($message);
							}
						}
					}

					return $this->response->redirect('forum/discussion/' . $post->id . '/' . $post->slug . '#C' . $postReply->id);
				}

				foreach ($postReply->getMessages() as $message) {
					$this->flash->error($message);
				}
			}
		}

		/**
		 * Set the post name as title - escaping it first
		 */
		$this->tag->setTitle($this->escaper->escapeHtml($post->title) . ' - Discussion');

		$this->view->post = $post;
	}

	/**
	 * Shows the latest modification made to a post
	 */
	public function historyAction($id = 0)
	{

		$this->view->disable();

		/**
		 * Find the post using get
		 */
		$post = Posts::findFirstById($id);
		if (!$post) {
			$this->flashSession->error('The discussion does not exist');
			return $this->response->redirect();
		}

		$a = explode("\n", $post->content);

		$first = true;
		$postHistories = PostsHistory::find(array('posts_id = ?0', 'bind' => array($post->id), 'order' => 'created_at DESC'));
		if (count($postHistories) > 1) {
			foreach ($postHistories as $postHistory) {
				if ($first) {
					$first = false;
					continue;
				}
				break;
			}
		} else {
			$postHistory = $postHistories->getFirst();
		}

		if (is_object($postHistory)) {
			$b = explode("\n", $postHistory->content);

			$diff = new \Diff($b, $a, array());
			$renderer = new \Diff_Renderer_Html_SideBySide();

			echo $diff->Render($renderer);
		} else {
			$this->flash->notice('No history available to show');
		}
	}

	/**
	 * Votes a post up
	 */
	public function voteUpAction($id = 0)
	{
		$response = new Response();

		/**
		 * Find the post using get
		 */
		$post = Posts::findFirstById($id);
		if (!$post) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'Post does not exist'
			));
		}

		$user = Users::findFirstById($this->session->get('identity'));
		if (!$user) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'You must log in first to vote'
			));
		}

		if ($user->votes <= 0) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'You don\'t have enough votes available'
			));
		}

		if (PostsVotes::count(array('posts_id = ?0 AND users_id = ?1', 'bind' => array($post->id, $user->id)))) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'You have already voted this post'
			));
		}

		$postVote = new PostsVotes();
		$postVote->posts_id = $post->id;
		$postVote->users_id = $user->id;
		if (!$postVote->save()) {
			foreach ($postVote->getMessages() as $message) {
				return $response->setJsonContent(array(
					'status' => 'error',
					'message' => $message->getMessage()
				));
			}
		}

		$post->votes_up++;
		if ($post->users_id != $user->id) {

			$post->user->karma += 5;
			$post->user->votes_points += 5;

			$user->karma += 10;
			$user->votes_points += 10;
		}

		if ($post->save()) {
			$user->votes--;
			if (!$user->save()) {
				foreach ($user->getMessages() as $message) {
					return $response->setJsonContent(array(
						'status' => 'error',
						'message' => $message->getMessage()
					));
				}
			}
		}

		return $response->setJsonContent(array(
			'status' => 'OK'
		));
	}

	/**
	 * Votes a post down
	 */
	public function voteDownAction($id = 0)
	{
		$response = new Response();

		/**
		 * Find the post using get
		 */
		$post = Posts::findFirstById($id);
		if (!$post) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'Post does not exist'
			));
		}

		$user = Users::findFirstById($this->session->get('identity'));
		if (!$user) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'You must log in first to vote'
			));
		}

		if ($user->votes <= 0) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'You don\'t have enough votes available'
			));
		}

		if (PostsVotes::count(array('posts_id = ?0 AND users_id = ?1', 'bind' => array($post->id, $user->id)))) {
			return $response->setJsonContent(array(
				'status' => 'error',
				'message' => 'You have already voted this post'
			));
		}

		$postVote = new PostsVotes();
		$postVote->posts_id = $post->id;
		$postVote->users_id = $user->id;
		$postVote->save();

		$post->votes_down++;
		if ($post->users_id != $user->id) {

			$post->user->karma -= 5;
			$post->user->votes_points -= 5;

			$user->karma += 10;
			$user->votes_points += 10;
		}

		if ($post->save()) {
			$user->votes--;
			if (!$user->save()) {
				foreach ($user->getMessages() as $message) {
					return $response->setJsonContent(array(
						'status' => 'error',
						'message' => $message->getMessage()
					));
				}
			}
		}

		return $response->setJsonContent(array(
			'status' => 'OK'
		));
	}

	/**
	 * Shows the latest activity on the IRC
	 */
	public function ircAction()
	{

		$irclog = IrcLog::find(array(
			'order' => 'datelog DESC',
			'limit' => 250
		));

		$activities = array();
		foreach ($irclog as $log) {
			$who = explode('@', $log->who);
			$nick = $who[0];
			$parts = explode('!', $who[0]);
			$log->who = substr($parts[0], 1);
			$activities[] = $log;
		}

		$this->view->activities = array_reverse($activities);

		$this->tag->setTitle('Recent Activity on the IRC');
	}

	/**
	 * Shows the latest activity on the forum
	 */
	public function activityAction($offset=0)
	{

		$this->view->total = Activities::count();

		$this->view->activities = Activities::find(array(
			'order' => 'created_at DESC',
			'limit' => array('number' => 40, 'offset' => 0)
		));

		$this->tag->setTitle('Recent Activity on the Forum');
	}

	/**
	 * Perform the search of posts only searching in the title
	 */
	public function searchAction()
	{

		$this->tag->setTitle('Search Results');

		list($itemBuilder, $totalBuilder) = $this->prepareQueries();

		$q = $this->request->getQuery('q');

		$queryTerms = '%'.preg_replace('/[ \t]+/', '%', $q).'%';

		$totalBuilder->where('p.title LIKE ?0');

		$itemBuilder->where('p.title LIKE ?0')
			->orderBy('p.created_at DESC');

		$posts = $itemBuilder->getQuery()
			->execute(array($queryTerms));

		if (!count($posts)) {
			$this->flashSession->notice('There are no search results');
			return $this->response->redirect();
		}

		$totalPosts = $totalBuilder
			->getQuery()
			->setUniqueRow(true)
			->execute(array($queryTerms));

		$this->view->posts = $posts;
		$this->view->totalPosts = $totalPosts;
		$this->view->currentOrder = null;
		$this->view->offset = 0;
		$this->view->paginatorUri = 'search';
	}

	/**
	 * Shows the user profile
	 */
	public function userAction($id, $username)
	{
		if ($id) {
			$user = Users::findFirstById($id);
		} else {
			$user = Users::findFirstByLogin($username);
			if (!$user) {
				$user = Users::findFirstByName($username);
			}
		}

		if (!$user) {
			$this->flashSession->error('The user does not exist');
			return $this->response->redirect();
		}

		$this->view->user = $user;

		$this->view->numberPosts = Posts::count(array(
			'users_id = ?0 AND deleted = 0',
			'bind' => array($user->id)
		));

		$this->view->numberReplies = PostsReplies::count(array(
			'users_id = ?0',
			'bind' => array($user->id)
		));

		$this->view->activities = Activities::find(array(
			'users_id = ?0',
			'bind' => array($user->id),
			'order' => 'created_at DESC',
			'limit' => 15
		));

		$this->tag->setTitle('Profile');
	}

	public function settingsAction()
	{

		$usersId = $this->session->get('identity');
		if (!$usersId) {
			$this->flashSession->error('You must be logged first');
			return $this->response->redirect();
		}

		$user = Users::findFirstById($usersId);
		if (!$user) {
			$this->flashSession->error('The user does not exist');
			return $this->response->redirect();
		}

		if ($this->request->isPost()) {
			$user->timezone = $this->request->getPost('timezone');
			$user->notifications = $this->request->getPost('notifications');
			if ($user->save()) {
				$this->session->get('timezone', $user->timezone);
				$this->flashSession->success('Settings were successfully updated');
				return $this->response->redirect('forum/');
			}
		} else {
			$this->tag->displayTo('timezone', $user->timezone);
			$this->tag->displayTo('notifications', $user->notifications);
		}

		$this->tag->setTitle('My Settings');
		$this->tag->setAutoEscape(false);

		$this->view->user = $user;
		$this->view->timezones = require '../app/config/timezones.php';

		$this->view->numberPosts = Posts::count(array(
			'users_id = ?0 AND deleted = 0',
			'bind' => array($user->id)
		));

		$this->view->numberReplies = PostsReplies::count(array(
			'users_id = ?0',
			'bind' => array($user->id)
		));
	}

	public function helpAction()
	{
		$this->response->redirect('forum/discussion/1/welcome-to-the-forum');
	}
}
