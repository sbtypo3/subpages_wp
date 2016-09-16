# subpages_wp
A plugin for Wordpress that adds a filter to view just subpages of another page.

This plugin works for all hierarchical post types, not just pages. If you don't see the filter either post type is not hierarchical or there are no posts with subpages.

The list of pages that the filter shows are cached in Wordpress' transient cache so that pages are not looked for when using the page browser. When a hierarchical post is updated, the cache of pages for that post type are removed from the transient cache.
