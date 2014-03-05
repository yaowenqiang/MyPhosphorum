{{ content() }}

<div class="view-discussion container">

	<p>
		<h1>Recent Activity</h1>
	</p>

	<ul class="nav nav-tabs">
		{% set orders = ['': 'Forum', '/irc': 'IRC'] %}
		{% for order, label in orders %}
			{% if order == '' %}
			<li class="active">
			{% else %}
			<li>
			{% endif %}
			{{ link_to('forum/activity' ~ order, label) }}
			</li>
		{% endfor %}
	</ul>

	<table width="90%" align="center" class="table table-striped">
		{% for activity in activities %}
		<tr>
			<td class="small" valign="top">
				<img src="https://secure.gravatar.com/avatar/{{ activity.user.gravatar_id }}?s=24&amp;r=pg&amp;d=identicon" class="img-rounded">
			</td>
			<td>
				<div class="activity">
					<span>{{ link_to('forum/user/' ~ activity.user.id ~ '/' ~ activity.user.login, activity.user.name|e) }}</span>

					{% if activity.type == 'U' %}
					has joined the forum
					{% elseif activity.type == 'P' %}
					has posted {{ link_to('forum/discussion/' ~ activity.post.id ~ '/' ~ activity.post.slug, activity.post.title|e) }}
					{% elseif activity.type == 'C' %}
					has commented in {{ link_to('forum/discussion/' ~ activity.post.id ~ '/' ~ activity.post.slug, activity.post.title|e) }}
					{% endif %}

					<span class="date">{{ activity.getHumanCreatedAt() }}</span>
				</div>
			</td>
		</tr>
		{% endfor %}
	</table>

</div>
