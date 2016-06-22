VIEWS
=====

Explanation for other developers

Let's say the MembersView handles your typical child page that members will see (e.g. with "my account" link at top, other info, alternate footer, etc.).

1. MembersView extends View.
2. MembersView has stored base layout of: app/templates/main/members.tpl
3. Let's say we're viewing "/account/view" - Account (controller), run_view() method.
4. The Account controller is set to use MembersView, and run_view() will manually call the 'account/view.tpl' template (app/templates/account/view.tpl).
	* Maybe 'account' is stored separately in the Account controller class.
