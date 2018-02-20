Pico edit - An admin interface for Pico CMS
===========================================

A small Back-end for Pico CMS.

Install
-------

1. Clone the Github repo into your 'plugins' directory (so you get a 'pico_edit' subdirectory) OR extract the zip into your 'plugins' directory (and rename the new directory to 'pico_edit')
2. Open the Pico config.php file and insert your sha256 hashed password
3. Visit http://www.yoursite.com/pico_edit and login

If pages editing doesn't work check file/dir permissions of 'content' folder.

About
-----

Pico Edit provides a back-end interface to edit Pico pages. Additionally, it has the ability to perform some basic Git operations such as commit, push/pull etc.

Features:

* Simple and clean interface

* Page create/edit/delete

* Markdown preview (top right icon in the editor)

* Edit 404 page (aka "page not found")

* Edit Pico options

Options (Pico "config/config.php"):

	$config['pico_edit_404'] = true;
	$config['pico_edit_options'] = false;			// Disallow options editing
	$config['pico_edit_default_author'] = 'Me';		// Default author for new pages

![Screenshot](https://github.com/blocknotes/pico_edit/blob/master/screenshot.png)

Editing Pico options
--------------------

To override a string option simply write a line with: `theme = default`

To override a boolean option use: `my_option ! true`

Other types of options are not supported.

Git functions
-------------

The general use-case is for one or more content editors to have a Git repo cloned onto their laptops. They can then go ahead and create or edit content, saving it to their local machine as required. When they're happy, they can commit to their local Git repo. How they publish the content is up to the administrator, but one method is to have a post-update hook on the Git server that publishes the content into the DocumentRoot of the webserver(s). Obviously, editors can Git-pull the changes other editors have made to their local machines so that they stay up to date. Depending on how you set things up, it's possible editors could even edit pages directly on the public website (and commit those to the Git repo from there).

Git features are only shown in the editor UI if the server has a Git binary available, and the content is in a Git repo. Push/pull functions are only available if the repo has one or more remote servers configured into it.

History
-------

* Pico Edit is a fork + modifications of [Peeked](https://github.com/coofercat/peeked). It contains minor improvements and some new feature like ability to edit 404 page and Pico options.

* Peeked is a fork + modifications of the [Pico Editor](https://github.com/gilbitron/Pico-Editor-Plugin), written by [Gilbert Pellegrom](https://github.com/gilbitron). It contains a few bug fixes and some functional changes, most particularly the addition of some Git capabilities.
