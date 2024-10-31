=== photostream-sync ===
Contributors: apokalyptik, shaunandrews, enej
Donate link: http://www.nokidhungry.org/give/overview
Tags: icloud, photostream, images, sync, import, apple, ios, osx, iphone, ipad, gallery, images, synchronize, mirror, photo, photos, photoblog, image
Requires at least: 3.8
Tested up to: 3.8
Stable tag: 2.1.2
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Synchronize your public iCloud photostreams to your WordPress installation. Import images, create gallery posts, and more.

== Description ==

Lets say, for the sake of argument that you have something like an iPhone. And you use it to take pictures.  You sure wish there was an easy way to be able to set up a WordPress blog as a photoblog automatically.  Now you can.  The <a href="http://www.apple.com/icloud/features/photo-stream.html">Photo Stream</a> iCloud feature is just for this kind of task.  You can sync to your iPhoto app, you can share to your friends, and even your TV. Now with this plugin you can have your WordPress blog automatically discover new photos shared from your iOS device and import them.

Just set up a Photo Stream. Configure it so that it has a "Public Web Site". And this plugin will take care of the rest for you.

* Shared a group of photos all at once? This plugin automatically makes a gallery post containing all the photos you shared together. 
* Did you add a comment to your shared photos? This plugin will automatcially add your text to the gallery post.  
* Don't like the idea of just anybody seeing your pictures? You can Password protect your posts
* Do you want to edit, rename, and curate your posts before they go live? You can have the plugin just make draft posts waiting for you to jazz them up
* Are you a theme or plugin developer? Use custom post types, tags, categories, and automatically indexed EXIF metadata from the photos to make an amazing photoblog.
* Don't want to sit there clicking buttons to make photos show up? This plugin uses WordPress scheduled work functionality to gradually import photos without needing your help.

== Frequently Asked Questions ==

= I installed the plugin when adding a stream it takes forever to import the media on the import screen? =

It might be that your server has running out of memory. You can try increasing the available php memory to your server. 
This plugin might help http://wordpress.org/plugins/memory-bump. 

= What happends if I leave/refresh the import media page = 

Photostream will not create the posts and media that have been imported already but will try to fetch the media that is not available in the database. However feel free to let it run in another tab while you browser the internet in diffrent browser window. You can see see the progress percentage in the tab. 

= How does WordPress Syncing the photostream from iCloud =

Photostream relies on the WordPress Cron to update the media and galleries with new content that you have posted to your iCloud stream.

This means that if your website doesn't recieve much traffic your new content will not be imported as frequently. Since traffic to your site is what triggers the WordPress cron to do work. 

You can always trigger a content refresh by clicking on the import link. 


= I installed the plugin, added one or more streams, but I have no new posts. Why? ( pre 2.1 )=

This plugin uses uses WordPress built in work scheduling functionality to get its work done. It should try to do work every 15 minutes or so, but there are a couple of caveats.  WordPress only does this work when people are visiting your site, and so if you're not getting a lot of "foot traffic" then it may take some time for the plugin to do its magic. Most times publicly indexed (by search engines like google) sites should manage to stay more or less up to date with the work that they need to do unless the traffic on the photostream is very heavy.

In cases where you don't want to wait for traffic, or your photostreams are very busy and you want instant updates you can take the advice of the following article to help make WordPress' scheduled work feature more... on schedule...

* http://bitswapping.com/2010/10/using-cron-to-trigger-wp-cron-php/

= What do I put into the "photostream" field? =

Lets say that your photostream url is https://www.icloud.com/photostream/#nnnnnnnnnnnnn.  You can put in "https://www.icloud.com/photostream/#nnnnnnnnnnnnn" or "nnnnnnnnnnnnn".  The plugin will validate what you put in as best as possible and try to figure out what you mean (and verify it by trying to fetch the stream from icloud.com) for you.

== Installation ==

= The plugin =
1. Install the plugin
1. Activate it

= The Photo Streams (iPhone) =
1. Open the photos app
1. Tap "Photo Stream" (bottom)
1. Tap + (top)
1. Give it a name, make sure "Public Website" is "On"
1. Tap Create (top)
1. Back at the list get the details for this new Stream (tap the blue right arrow)
1. Down at the bottom is the link to the photostream. 

= Adding Your Photo Stream =
1. Open your wp-admin
1. Media -> Photostreams
1. Enter that link URL, or just the part after the # for "PhotoStream" under the "Add A Stream" tab in the plugin page

== Screenshots ==

1. Plugin Dashboard. Start by adding a photostream.
2. Edit Photostream Page.
3. Delete Photostream Page. 
4. Adding a Photostream Page.
5. Import Photostream Page. 


== Changelog ==
= 2.1.1 =
* Fix a bug where a valid url would fail to validate when attempting to add it to the site.

= 2.1 =
* Updated the plugin user interface to match the modern mobile friendly design introduced in WordPress 3.8.
* Added the ability to import the photostream on request.
* Simplefied the stream configuration page.
* Improvment to adding a photostream process. 

= 2.0.4 =
* Added the ability to customize the video shortcode added to the gallery post content for imported videos.

= 2.0.3 =
* Honor "Gallery Publishing" setting once again (probably unnoticed since most people likely prefer to go directly to publish with imported media)
* Should fix "Fatal error: Call to undefined function wp_read_video_metadata() in …/wp-admin/includes/image.php on line 122")

= 2.0.2 =
* Fix an admin-breaking bug which caused some things (like the multi-uploader) not to work properly with previous versions of the plugin installed

= 2.0.1 =
* added support for custom gallery shortcodes

= 2.0.0 =
* Added video support

= 1.0.3 =
* Set gallery post format to gallery

= 1.0.2 =
* usability improvements (props Shaun Andrews)

= 1.0.1 =
* attach stream key to posts via postmeta
* configurable caption use

= 1.0 =
* Initial Release

== Upgrade Notice ==

= 1.0 =
* Because, you know. NULL was not a good version.

= 1.0.1 =
* Images, and gallery posts from 1.0 will not have the photostream key (metadata) attached to them

= 2.0.0 =
* Videos will only be imported moving forward. There is, currently, no way to backfill videos which were skipped over previously. Also image placeholders for videos will no longer be imported as images, seeing as the video is being imported now.

= 2.0.2 =
* This upgrade is highly suggested for all users of the plugin.  It fixes a severe (though not exploitable) bug where the plugin caused some things in the WP admin not to work properly.

= 2.1 =
* This update is suggested if you are running WP 3.8 or later and want to improve have a better user experience. 

== How this works in relation to iCloud ==

Warning: This section is pretty hardcore 

iCloud publishes a JSON endpoint for its Photo Streams.  This endpoint has a list of photos (but not urls for the photos) and data about them (like what group they belong to, who posted them, etc.) That endpoint gives you enough information to make an HTTP POST request to another URL at which you can find enough information to build the image fetch URLS.  The data is only good for a limited amount of time (the urls that you build with this expire) and so it is necessary that you build this fresh when you're going to use it.  This is also why you can't just import a list of photo urls and call it a day.  Finally you can make a final HTTP request per image to get the data.

Essentially this plugin just does what your web browser would do if you visited the public photostream link.  

I have not looked further into how likes, and additional comments on photos are stored.  The data must be in there.

== For theme developers ==

For theme developers there's not much to know.  You can specify any cats, or tags, you desire for a photostream.  You can also specify custom post types. So you have a lot of flexibility.  Finally, if the PHP Exif extension is installed and active ( http://www.php.net/exif ), then the plugin stores all EXIF metadata that if finds as postmeta data for the image attachment itself. For example: 

* ps_exif_Make => Apple
* ps_exif_Model => iPhone 4S
* ps_exif_XResolution => 72/1
* ps_exif_YResolution => 72/1
* ps_exif_ResolutionUnit => 2

== To Do List ==

* Logging (via custom post type, I think)
* Error reporting, and handling (probably also via custom post type)
* Add history/post/image listing to stream pages (essentially an activity log)
* Add feedback for "dev mode" process now clicks
* Make some of the dev mode things just things people can do (force-process stream, for example)
* Add images captions/notes/whatever to gallery posts as text (when you push a group of photos to a stream you can supply text)
* See about importing comments as comments on the gallery with reference to the specific image (people can comment and like your photos)
* Work within PHPs time limits (Fatal error, time limit of %d seconds exceeded)
* uninstaller
* Add cats from the add/manage section of the admin UI
