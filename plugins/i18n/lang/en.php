<?php
/**
 * English translations (default fallback).
 *
 * Keys are dotted. To add a new key, add the same key to every other
 * language file. Missing keys fall back to the English value.
 */
return [
    // Site
    'site.name'        => 'wawoo-cms',
    'site.tagline'     => 'A lightweight, themeable, plugin-extensible blog in PHP.',

    // Navigation
    'nav.home'         => 'Home',
    'nav.search'       => 'Search',
    'nav.rss'          => 'RSS',
    'nav.sitemap'      => 'Sitemap',
    'nav.admin'        => 'Admin',
    'nav.logout'       => 'Logout',
    'nav.back_home'    => 'Back to home',

    // Common
    'common.read_more' => 'Read more',
    'common.no_posts'  => 'No posts yet.',
    'common.no_results'=> 'No results.',
    'common.search'    => 'Search',
    'common.submit'    => 'Submit',
    'common.loading'   => 'Loading...',
    'common.tags'      => 'Tags',
    'common.categories'=> 'Categories',
    'common.category'  => 'Category',
    'common.tag'       => 'Tag',
    'common.by'        => 'by',
    'common.author'    => 'Author',
    'common.posted'    => 'Posted',
    'common.recent'    => 'Recent posts',
    'common.older'     => 'Older',
    'common.newer'     => 'Newer',
    'common.next'      => 'Next &raquo;',
    'common.prev'      => '&laquo; Prev',
    'common.page'      => 'Page %d / %d',

    // Post
    'post.published_on'=> 'Published on',
    'post.by'          => 'by',
    'post.category'    => 'Category',
    'post.tagged'      => 'Tagged',
    'post.related'     => 'Related posts',

    // Pagination
    'pagination.first' => 'First',
    'pagination.last'  => 'Last',
    'pagination.of'    => 'of',

    // 404
    '404.title'        => 'Page not found',
    '404.message'      => 'The page you requested does not exist.',
    '404.back'         => 'Back to home',
    'e404.title'       => 'Not found',
    'e404.body'        => 'The page you requested does not exist.',
    'e404.home'        => 'Back to home',

    // Search
    'search.title'     => 'Search',
    'search.placeholder'=> 'Search...',
    'search.results_for' => 'Results for',
    'search.results'   => '%1$d result(s) for <strong>%2$s</strong>:',
    'search.empty'     => 'No results for <strong>%s</strong>.',
    'search.no_query'  => 'Type a query above.',

    // Sitemap
    'sitemap.total'    => '%1$d posts in total.',

    // Comments
    'comments.title'   => 'Comments',
    'comments.count'   => 'comment(s)',
    'comments.name'    => 'Name',
    'comments.email'   => 'Email (optional)',
    'comments.website' => 'Website (optional)',
    'comments.body'    => 'Comment',
    'comments.submit'  => 'Submit',
    'comments.pending' => 'Comment submitted, awaiting moderation.',
    'comments.posted'  => 'Comment posted.',

    // Admin
    'admin.dashboard'  => 'Dashboard',
    'admin.plugins'    => 'Plugins',
    'admin.themes'     => 'Themes',
    'admin.enable'     => 'Enable',
    'admin.disable'    => 'Disable',
    'admin.settings'   => 'Settings',
    'admin.publish'    => 'Publish',
    'admin.schedule'   => 'Schedule',
    'admin.toggle'     => 'Toggle',
    'admin.delete'     => 'Delete',
    'admin.confirm_delete' => 'Delete %s?',
    'admin.title'      => 'Admin - %s',
    'admin.posts'      => 'Posts',
    'admin.categories' => 'Categories',
    'admin.tags'       => 'Tags',
    'admin.no_posts'   => 'No posts yet. Drop a markdown file into <code>posts/</code> to get started.',
    'admin.login_title'=> 'Admin login',
    'admin.password'   => 'Password',
    'admin.sign_in'    => 'Sign in',
    'admin.disabled'   => 'Admin disabled',
    'admin.disabled_hint' => 'Set ADMIN_PASSWORD in config.local.php to enable admin.',
    'admin.active'     => 'Active',
    'admin.activate'   => 'Activate',
    'admin.name'       => 'Name',
    'admin.version'    => 'Version',
    'admin.description'=> 'Description',
    'admin.status'     => 'Status',
    'admin.status.published' => 'published',
    'admin.status.draft'     => 'draft',
    'admin.actions'    => 'Actions',
    'admin.date'       => 'Date',
    'admin.logout'     => 'Logout',
    'admin.title_col'  => 'Title',
    'admin.stats'      => '<strong>%1$d</strong> posts, <strong>%2$d</strong> categories, <strong>%3$d</strong> tags.',

    // Password gate
    'gate.title'       => 'Members only',
    'gate.message'     => 'This site is private. Enter the password to continue.',
    'gate.placeholder' => 'Password',
    'gate.button'      => 'Enter',
    'gate.footer'      => 'Authorized access only.',
    'gate.incorrect'   => 'Incorrect password.',
    'gate.too_many'    => 'Too many attempts. Try again later.',

    // Dates
    'date.year_month'  => 'F Y',
    'date.full'        => 'Y-m-d H:i',
];
