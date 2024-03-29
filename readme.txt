=== Embed PDF for WPForms ===

Contributors: salzano
Tags: wpforms, wp forms, pdf, inkless
Requires at least: 4.0
Tested up to: 6.4.2
Requires PHP: 5.6
Stable tag: 1.1.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
 
An add-on for WPForms. Provides a PDF Viewer field.


== Description ==
 
Embed PDF for WPForms provides a PDF Viewer field type. Include PDF files in forms without requiring users to download the PDF. Supports multi-page documents for PDF flipbooks in WPForms. Provides zoom controls.

= Features =

* Drag a PDF Viewer field onto any WPForm
* Choose PDF from Media Library or provide local URL
* Set default zoom level
* Supports multi-page PDFs
* Supports Dynamic Population

= Demo =

[https://breakfastco.xyz/embed-pdf-for-wpforms/](https://breakfastco.xyz/embed-pdf-for-wpforms/)

Have an idea for a new feature? Please create an Issue on Github or Support Topic on wordpress.org.


== Installation ==
 
1. Search for Embed PDF for WPForms in the Add New tab of the dashboard plugins page and press the Install Now button
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Open the form editor through the 'WPForms' menu in WordPress
1. Add a 'PDF Viewer' field from the Standard Fields tab in the form editor.

= pdfjs-dist package =

This plugin includes https://www.npmjs.com/package/pdfjs-dist, which contains combined and minimized scripts built by the `gulp dist` command in https://github.com/mozilla/pdf.js. No unusual or additional action is required to download or install this plugin. If you wish to obtain the unminimized scripts or build pdfjs-dist yourself, use these commands:

git clone https://github.com/mozilla/pdf.js
cd pdf.js
npm install
gulp dist

== Frequently Asked Questions ==
 
= How can I suggest a new feature for this plugin? =
 
Please create an [Issue on Github](https://github.com/breakfastco/embed-pdf-wpforms/issues) or Support Topic on wordpress.org.


== Screenshots ==

1. Screenshot of a PDF embedded in a WPForm. 
1. Screenshot of a PDF Viewer field type in the WPForms form builder.
1. Screenshot of the form builder sidebar showing the PDF Viewer field settings.


== Changelog ==

= 1.1.3 =
* [Fixed] Adds compatibility with language packs for translation. For real this time.

= 1.1.2 =
* [Fixed] Adds compatibility with language packs for translation.
* [Changed] Changes the detail sent in JavaScript event epdf_gf_render_page from an integer to an object.
* [Changed] Changes tested up to version to 6.4.2.

= 1.1.1 =
* [Added] Adds comments explaining what pdfjs-dist is and where the unminimized files are available.
* [Changed] Changes tested up to version to 6.4.1.

= 1.1.0 =
* [Added] Adds a Download PDF into Media Library button to the CORS error messages for users that have the upload_files capability.
* [Fixed] Fixes the Choose PDF button not working for users without access to the Media Library by telling users why it does not work. The upload_files capability is required to use the Media Library dashboard features like the modal this button opens.
* [Fixed] Avoid errors when two copies of this plugin are activated at the same time.
* [Fixed] Adds a "file not found" error to the form editor so users know that PDF files are missing without previewing the form.
* [Fixed] Changes CSS so the previous, next, zoom in, and zoom out buttons look better on smaller screens.
* [Changed] Changes the tested up to version to 6.3.2.

= 1.0.1 =
* [Fixed] Moves inline JavaScript required for each PDF Viewer field to the wpforms_frontend_js hook.
* [Fixed] Stops writing errors to the browser developer console unless SCRIPT_DEBUG is enabled.

= 1.0.0 =
* [Added] First version.


== Upgrade Notice ==

= 1.1.3 =
Adds compatibility with language packs for translation.

= 1.1.2 =
Adds compatibility with language packs for translation. Changes the detail sent in JavaScript event epdf_gf_render_page from an integer to an object. Changes tested up to version to 6.4.2.

= 1.1.1 =
Adds comments explaining what pdfjs-dist is and where the unminimized files are available. Changes tested up to version to 6.4.1.

= 1.0.0 =
First version.