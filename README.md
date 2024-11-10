Fancy Treeview for webtrees
===========================

[![Latest Release](https://img.shields.io/github/release/JustCarmen/webtrees-fancy-treeview.svg)][1]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)][2]
[![Downloads](https://img.shields.io/github/downloads/JustCarmen/webtrees-fancy-treeview/total.svg)]()

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=XPBC2W85M38AS&item_name=webtrees%20modules%20by%20JustCarmen&currency_code=EUR)

Introduction
-----------
**Quick overview of a person's family tree**\
This module gives your users a quick overview of a person's family tree. Of course, privacy rules are respected.

**Additonal tab “Descendants and Ancestors”**\
This module adds an additional tab “Descendants and Ancestors” to the individual page.

This tab shows in a narrative way the details of the current person and his/her descendants in the next two generations. You can also get an overview of ancestors. With one click you switch to the person's ancestors in the previous two generations.

**Read more on the Fancy Treeview page**\
If more generations are available, they can be accessed by clicking the read more button. This button redirects to a separate page, the Fancy Treeview page. This page displays all generations from the current person to the last accessible generation (up or down) in a narrative manner. You can use the page navigation at the bottom to browse through all the ancestors/descendants in the family tree.

**Indication of completeness of ancestry**\
In addition to the usual information, the ancestor summary also shows a percentage indicating how complete the generation is.  The 1st generation consists of 1 person (the starting person), the 2nd generation consists of the parents of the starting person (up to 2 people), the next generation consists of the grandparents of the starting person (up to 4 people), and so on. Family tree collapse is also taken into account. In genealogy, pedigree collapse describes how procreation between two individuals who share an ancestor causes the number of different ancestors in the pedigree of their descendants to be smaller than it would otherwise be. Without collapse of the pedigree, the 10th generation is complete if it consists of 512 individuals (2^(10-1)). To use this feature, you must set the “Check kinship between partners” option.

**Menu or home page block**\
A fancy treeview menu or home page block is also available. The administrator can add or remove pages from the menu and/or home page block. To do so, use the button at the top of the Fancy Treeview page or the links in the edit menu on the individual page.

**Configuration page**\
On the configuration page, you can set a number of options to customize the Fancy Treeview page and/or tab.

**Print the Fancy Treeview page as pdf**\
It is possible to download the Fancy Treeview page as a pdf. For this you can use your browser's native print function. Use ctrl. P to open the print dialog. You can then choose to print the page as a pdf. The module includes print styles that strip the printout of clutter. The print styles work best with Chrome or Microsoft Edge. The disadvantage of this method is that you can only print the current page. To print the entire tree, you need to increase the number of generations per page in the settings. Only administrators can change settings.

Translations
------------
You can help translate this module. The language files are available on [POEditor][3] where you can update them. But you can also use a local editor such as Poedit or Notepad++ to create the translations. Then send them to me by e-mail or make a pull request (if you know how). Updated translations will be included in the next release of this module.

Installation & upgrading
------------------------
Unzip the zip file and place the jc-fancy-treeview folder in the modules_v4 folder of webtrees. Upload the newly added folder to your server. It is activated by default. Go to an individual page and click on the Descendants and Ancestry tab.

Bugs and feature requests
-------------------------
If you encounter bugs or have a feature request for this module, you can [create a new issue on GitHub][4].

 [1]: https://github.com/JustCarmen/webtrees-fancy-treeview/releases/latest
 [2]: https://webtrees.github.io/download/
 [3]: https://poeditor.com/join/project/9HqYANIknp
 [4]: https://github.com/JustCarmen/webtrees-fancy-treeview/issues?state=open


