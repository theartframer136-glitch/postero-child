=== Language Switcher for Transposh ===
Contributors: codingfix
Donate link: https://www.paypal.com/paypalme/codingfix
Tags: transposh, language switcher, multi-language, flags, translation
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 2.0.6
Requires PHP: 5.6
Requires Plugins: transposh-translation-filter-for-wordpress
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A professional, highly customizable language switcher for Transposh. Requires Transposh Translation Filter plugin to be installed.

== Description ==

**IMPORTANT: READ BEFORE INSTALLING**
Language Switcher for Transposh (LSfT) is **not** a translation engine. It is an advanced styling and integration tool that provides a beautiful, customizable interface for the [Transposh Translation Filter plugin](https://transposh.org/download).

**LSfT REQUIRES TRANSPOSH TO WORK.** It will not translate your content on its own and cannot be activated unless Transposh is already installed and active.

**How to get started:**
1. **First:** Download, install, and activate [Transposh Translation Filter](https://transposh.org/download). Configure your languages in the Transposh settings.
2. **Second:** Install and activate **Language Switcher for Transposh**. You can now choose how to display your flags and language names.

**Main Features:**
* **Integrated CSS Editor (New in 2.0):** Customize the switcher's appearance directly from the admin panel with a professional code editor (CodeMirror).
* **Safe Customization:** Experiment with confidence! If something goes wrong with your custom styles, you can always restore the original plugin stylesheet with a single click.
* **Fast & Smart Loading:** Your custom styles are saved in the database for maximum performance, while keeping the original files untouched as a secure backup.
* **Flexbox Powered:** Modernized layout for perfect vertical and horizontal alignment in any theme.
* **Automode:** Automatically inject the switcher into your primary or selected WordPress menu.
* **Shortcodes & Widgets:** Full support for Shortcodes and Legacy Widgets, compatible with Classic and Block-based themes.
* **Admin Tools:** Adds an "Edit Translation" button for authorized roles (Admin, Author, Editor) to quickly toggle the Transposh Editor.

== Switcher Types ==
The version 2.0.0 provides 10 distinct switcher styles:
1.  **Horizontal:** Flags only or Code only.
2.  **Vertical:** Flags only ir Code only.
3.  **Dropdown (Custom JS):** Flags only, Text only, Code only or Flags and Text.
4.  **Native Select:** Lightweight browser-native dropdown: Code only or Text only.

== Installation ==
1. Ensure Transposh is installed and configured.
2. Upload the `cfx-language-switcher-for-transposh` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Navigate to **Settings > LSfT Settings** to choose your style.

== Frequently Asked Questions ==

= Why isn't it translating anything? =
Please check if Transposh is configured correctly. LSfT only handles the visual switcher. If Transposh is not translating, the issue is likely in the Transposh settings or your Permalinks.

= My switcher doesn't appear in the menu =
In Full Site Editing (FSE) themes, the "Automode" may not work. Please use the provided shortcodes or the widget in your navigation block.

= Avada Theme compatibility =
If you use Avada Theme Builder and the switcher doesn't appear, we recommend using the [Shortcode in Menus](https://wordpress.org/plugins/shortcode-in-menus/) plugin to manually place the LSfT shortcode in your header.

= Page Not Found (404) errors =
Ensure "Rewrite URLs to be search engine friendly" is enabled in Transposh Settings and your WordPress Permalinks are active.

== Changelog ==

= 2.0.6 =
* Added: class 'current-lang' to the current language item in horizontal and vertical switchers

= 2.0.5 =
* Fix: typo in admin “More Tools” page

= 2.0.4 =
* Fix: Corrected missing assets and deployment issues.

= 2.0.3 =
* Added: Included missing css file.

= 2.0.2 =
* Added: Included info about other tools.

= 2.0.1 =
* Fixed: Correctly initialized database options for existing users upgrading from previous versions.
* Improved: Smooth migration of settings to the new 2.0 architecture.

= 2.0.0 =
* **Major Refactoring:** Completely rewritten rendering engine for better performance and maintainability.
* **New Feature:** Integrated CSS Editor with CodeMirror support in the Admin panel.
* **New Feature:** Support for 10 different switcher styles (Horizontal, Vertical, Dropdown, Select).
* **Improved:** Modernized layouts using Flexbox for perfect alignment in the frontend.
* **Improved:** UI in the Admin dashboard is now more compact, more clear and easier to use.
* **Optimized:** Smart CSS loading logic (Database storage with automatic fallback to physical file).
* **Fixed:** Resolved registration issues with the Legacy Widget in WordPress 5.8+.
* **Fixed:** Cleaned up legacy code and unused methods.

= 1.8.0 =
* Fixed minor bugs, improved styles.

= 1.7.9 =
* Refactored code to update compatibility with latest PHP version.

= 1.7.8 =
* Added the option to display languages as codes both as single items and as custom list items.

= 1.7.7 =
* Fixed typos in UI.

= 1.7.6 =
* Fixed a css rule in basic_list.css to position submenu just below the nav bar.

= 1.7.5 =
* Tested up to WordPress 6.8.3.
* Fixed a bug loading css in Admin area.

= 1.7.4 =
* Tested up to WordPress 6.7.

= 1.7.3 =
* Fixed a typo in the readme file.

= 1.7.2 =
* Tested up 6.6.

= 1.7.1 =
* Tested up 6.6.

= 1.7.0 =
* Fixed css rules in horizontal flags.

= 1.6.9 =
* Fixed a bug that told to setup Transposh languages even if they were already set.

= 1.6.8 =
* Added Requires plugins clause in Readme.
* Improved activation process.

= 1.6.7 =
* Added bold warning about requiring Transposh plugin in short description.

= 1.6.6 =
* Fixed a bug preventing the user from returning and using the English flag.

= 1.6.5 =
* Fixed blocked access to Styles tab.
* Added option to choose between USA and British flags for english language.
* Added option to use default Transposh flags.

= 1.6.4 =
* Fixed wrong link to settings page in plugins page.

= 1.6.3 =
* Fixed a css bug in shortcodes stylesheets that added an extra padding to the left side of the page when using RTL languages.

= 1.6.2 =
* Added important notice in the readme file about the nature and the installation of the plugin.
* Fixed some minor bugs.

= 1.6.1 =
* Reviewed the whole code to fix errors found using Plugin Check tool.

= 1.6.0 =
* Fixed vulnerability to Cross Site Scripting (XSS).

= 1.5.9 =
* Added link to definitely dismiss notice about coupon code.

= 1.5.8 =
* Fixed a bug preventing the use of the plugin.

= 1.5.7 =
* Fixed a bug preventing correct updates.

= 1.5.6 =
* Integrated Freemius platform.
* Improved tab navigation in the settings page.
* Added a spinner while loading the selected style in the settings page.
* Refactored the code to copy to the clipboard the CSS editor content using the Clipboard API.

= 1.5.5 =
* Fixed a bug that prevented to load styles in the CSS viewer in the plugin settings page.

= 1.5.4 =
* Fixed a couple of minor bugs.
* Tested up to 6.4.

= 1.5.3 =
* Fixed minor bugs.

= 1.5.2 =
* Fixed a bug which prevented to see correctly the text of the select.

= 1.5.1 =
* Fixed a bug loading predefined stylesheets.

= 1.5.0 =
* Removed inline margin-bottom for the shortcode flag switcher.

= 1.4.9 =
* Fixed a bug preventing to redirect to the current page after having changed the active language.

= 1.4.8 =
* Added alt attribute to flag images to improve accessibility.

= 1.4.7 =
* Fixed unexpected output on plugin activation bug.

= 1.4.6 =
* Fixed the same bug of 1.4.5 for widgets.

= 1.4.5 =
* Fixed a bug which prevented native select to work correctly.

= 1.4.4 =
* Improved Edit translations button in primary menu.
* Fixed a bug which prevented to correctly load default styles in the CSS viewer.
* Fixed a bug which prevented to scroll CSS viewer.

= 1.4.3 =
* Increased specificity of css rules in Settings page to avoid conflicts.

= 1.4.2 =
* Fixed a bug that in some cases raised a 500 error loading stylesheets.

= 1.4.1 =
* Tested up 6.1.1.

= 1.4.0 =
* Tested up 6.0.2.
* Fixed a bug which prevented to switch to Styles tab in plugin options page.

= 1.3.9 =
* Tested up 6.0.1.

= 1.3.8 =
* Fixed a bug which prevented other tabs to work correctly in the admin dashboard.

= 1.3.7 =
* Fixed a bug which in some cases prevented to show default language in the frontend switcher.

= 1.3.6 =
* Minor fix.

= 1.3.5 =
* Fixed a bug which prevented to show the original language names switcher in admin options.

= 1.3.4 =
* Fixed bug in css editor which displayed an error message in browser console.

= 1.3.3 =
* Added option to display original languages names.
* Deleted obsolete css files.

= 1.3.2 =
* Fixed a bug which prevented the widget for custom list with flags and names to work correctly.

= 1.3.1 =
* Now Language Switcher for Transposh has its own widget you can use in the WordPress widget page.
* Fixed list-style-type for custom lists.

= 1.3.0 =
* Fixed a bug which prevented the shortcode for custom list with flags and names to work correctly.

= 1.2.9 =
* Fixed a bug which prevented widget to show up.

= 1.2.8 =
* Added the widget.
* Prevent activation if Transposh is not installed.
* Fixed the bug which prevented to download additional stylesheets.

= 1.2.7 =
* Fixed wrong shortcode names for horizontal and vertical flags.

= 1.2.6 =
* Improved the way the plugin checks versions and updates options.

= 1.2.5 =
* Added code to check plugin versions and call activation hook to update database options.

= 1.2.4 =
* Fixed a bug which prevented to download new stylesheets.

= 1.2.3 =
* Fixed a bug which prevented to load basic stylesheets.

= 1.2.2 =
* Fixed a bug about flag size.

= 1.2.1 =
* Added redirection options (Home page vs Same page).
* Added shortcodes for all switcher types.
* Improved custom styling capabilities.
* Tested up to 5.9.2.

= 1.0.23 =
* Tested up to 5.9.1.

= 1.0.22 =
* Tested up to 5.9.
* Fixed bugs preventing custom dropdown switcher use.

= 1.0.21 =
* Tested up to 5.8.9.

= 1.0.20 =
* Reverted changes to redirection. Now switching language redirects to the home page for stability.

= 1.0.19 =
* Minor bug fixed.

= 1.0.18 =
* Fixed a bug which broke link switching language.

= 1.0.17 =
* Now switching to another language reloads the current page.

= 1.0.16 =
* Fixed one minor bug.

= 1.0.15 =
* Added error messages for failed activation when Transposh is missing.

= 1.0.14 =
* Fixed minor bugs.

= 1.0.13 =
* Fixed minor bugs.

= 1.0.12 =
* Fixed minor bugs.

= 1.0.11 =
* Fixed a couple of typos.

= 1.0.10 =
* Fixed bug preventing displaying Settings link in the Plugins list.

= 1.0.9 =
* Added Settings link in the Plugins list admin page.

= 1.0.8 =
* Fixed a bug managing used languages.

= 1.0.7 =
* Fixed a bug managing default language.
* Fixed dropdown list item behavior based on current language.
* Improved default.css rules for dropdown lists.

= 1.0.6 =
* Fixed menu locations issue.

= 1.0.5 =
* Minor bug fixed.

= 1.0.4 =
* Improved README file, minor bugs fixed.

= 1.0.3 =
* Fixed bug saving custom styles.

= 1.0.2 =
* Improved css for menu locations.

= 1.0.1 =
* Added options to choose menus for the language switcher.

= 1.0.0 =
* Initial version of the plugin.