{{ content() }}

<div class="view-discussion container">

	<p>
		<h1>Recent Activity</h1>
	</p>

	<ul class="nav nav-tabs">
		{% set orders = ['': 'Forum', '/irc': 'IRC'] %}
		{% for order, label in orders %}
			{% if order == '' %}
			<li>
			{% else %}
			<li class="active">
			{% endif %}
			{{ link_to('forum/activity' ~ order, label) }}
			</li>
		{% endfor %}
	</ul>

	<table width="90%" align="center" class="table">
		{% for activity in activities %}
		<tr>
			<td class="medium" valign="top" width="15%">
				<span class="date">{{ date("Y/m/d h:i", activity.datelog )}}</span>
			</td>
			<td class="small" valign="top" width="10%">
				{{ activity.who }}
			</td>
			<td>
				{{ activity.content|e }}
			</td>
		</tr>
		{% endfor %}
	</table>

</div>
