Fancy Treeview for webtrees
===========================

[![Latest Release](https://img.shields.io/github/release/JustCarmen/webtrees-fancy-treeview.svg)][1]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)][2]
[![Downloads](https://img.shields.io/github/downloads/JustCarmen/webtrees-fancy-treeview/total.svg)]()

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=XPBC2W85M38AS&item_name=webtrees%20modules%20by%20JustCarmen&currency_code=EUR)

Introduction
-----------
This module is a rewritten version of the Fancy treeview module.

This new version of the module adds an additional tab "Descendants and Ancestors" to the individual page.

This tab shows in a narrative fashion the details of the current person and his/her descendants in the next two generations. What's new is that you can also get an overview of ancestors! With one click you switch to a person's ancestors in the next two generations.

If more generations are available, they are accessible by clicking the readmore link, which redirects to a separate page that displays all generations from the current person to the last accessible generation (up or down) in a narrative manner.

This gives your users a quick overview of the chosen person's family tree. Of course, privacy rules are respected.

The administrator can add or remove pages from the menu by clicking on the button at the top of the Fancy Treeview page or by using the links in the edit menu on the individual page.

There is a configuration page where you can set a few options to customize the Fancy Treeview page and/or tab to your needs.

In addition to the usual information, the ancestor overview also shows a percentage indicating the extent to which the generation is complete.  The 1st generation consists of 1 person, the 2nd generation consists of the parents of that person (up to 2 persons), the next generation consists of the grandparents of the starting person (up to 4 persons) and so on. It also takes into account pedigree collapse. In genealogy, pedigree collapse describes how reproduction between two individuals who share an ancestor causes the number of distinct ancestors in the family tree of their offspring to be smaller than it could otherwise be. Without pedigree collapse, the 10th generation is complete if it consists of 512 people (2^(10-1)). To use this feature you need to set the option 'Check relationship between partners'.

At the moment it is not possible to download the Fancy Treeview page as a pdf as in the webtrees 1 version of this module. It is possible that this will be re-implemented in a later version.

Translations
------------
You can help to translate this module. The language files are available at [POEditor][3] where you can update them. But you can also use a local editor, like Poedit or Notepad++ to make the translations and send them back to me. You can do this via a pull request (if you know how) or by e-mail. Updated translations will be included in the next release of this module.

Installation & upgrading
------------------------
Unpack the zip file and place the folder jc-fancy-treeview in the modules_v4 folder of webtrees. Upload the newly added folder to your server. It is activated by default. Go to any individual page and click on the tab 'Descendants and Ancestors'.

Bugs and feature requests
-------------------------
If you experience any bugs or have a feature request for this module you can [create a new issue on GitHub][4].

 [1]: https://github.com/JustCarmen/webtrees-fancy-treeview/releases/latest
 [2]: https://webtrees.github.io/download/
 [3]: https://poeditor.com/join/project/9HqYANIknp
 [4]: https://github.com/JustCarmen/webtrees-fancy-treeview/issues?state=open


