<header>
	<nav class="navbar navbar-reverse" role="navigation">
	  <div class="container-fluid">
		<div class="navbar-header">
		  <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
			<span class="sr-only">Toggle navigation</span>
			<span class="icon-bar"></span>
			<span class="icon-bar"></span>
			<span class="icon-bar"></span>
		  </button>
		  {{ link_to('forum/', 'Phosphorum', 'class': 'navbar-brand') }}
		</div>

		<div class="collapse navbar-collapse">
		  <ul class="nav navbar-nav navbar-right">
			{%- if session.get('identity') -%}
				<li>{{ link_to('forum/post/discussion', 'Start a Discussion', 'class': 'btn btn-default btn-info', 'rel': 'nofollow') }}</li>
			{%- else -%}
				<li>{{ link_to('forum/login/oauth/authorize', 'Log In with Github', 'class': 'btn btn-default btn-info', 'rel': 'nofollow') }}</li>
			{%- endif -%}
			<li>{{ link_to('forum/', '<span class="glyphicon glyphicon-comment"></span>', 'title': 'Discussions') }}</li>
			<li class="dropdown">
          		<a href="#" class="dropdown-toggle" data-toggle="dropdown" title="Search">
          			<span class="glyphicon glyphicon-search"></span> <b class="caret"></b>
          		</a>
          		<ul class="dropdown-menu">
					<li>
						<div style="width:300px">
							<gcse:searchbox-only></gcse:searchbox-only>
						</div>
					</li>
				</ul>
          	</li>
			<li>{{ link_to('forum/activity', '<span class="glyphicon glyphicon-eye-open"></span>', 'title': 'Activity') }}</li>

			<li class="dropdown">

				<a href="#" class="dropdown-toggle" data-toggle="dropdown" title="Categories">
					<span class="glyphicon glyphicon-th-list"></span> <b class="caret"></b>
				</a>

				{% cache "sidebar" %}
					<ul class="dropdown-menu">
						{% if categories is defined %}
							{% for category in categories %}
								<li>
									{{ link_to('forum/category/' ~ category.id ~ '/' ~ category.slug,
										category.name ~ '<span class="label label-default" style="float: right">' ~ category.number_posts ~ '</span>')
									}}
								</li>
							{% endfor %}
						{% endif %}
					</ul>
				{% endcache %}
			</li>

			<li>{{ link_to('forum/help', '<span class="glyphicon glyphicon-question-sign"></span>', 'title': 'Help') }}</li>

			{% if session.get('identity') %}
			<li>{{ link_to('forum/settings', '<span class="glyphicon glyphicon-cog"></span>', 'title': 'Settings') }}</li>
			<li>{{ link_to('forum/logout', '<span class="glyphicon glyphicon-off"></span>', 'title': 'Logout') }}</li>
			{% endif %}
		  </ul>
		</div>
	  </div>
	</nav>
</header>
