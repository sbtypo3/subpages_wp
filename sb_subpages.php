<?php
	/*
	Plugin Name:	SB Subpage Filter
	Plugin URI:	https://github.com/sbungert/sb_subpages/
	Description:	Adds a filter to hierarchical post listing tables allowing you to view only subpages of a specific page.
	Version:	1.0.0
	Author:		sbungert
	Author URI:	https://github.com/sbungert/
	License:	GPLv2
	*/
	
	
	
	/*  
	    Copyright 2016  Stephen Bungert  (email : sb.design.de@gmail.com)
	
	    This program is free software; you can redistribute it and/or modify
	    it under the terms of the GNU General Public License, version 2, as 
	    published by the Free Software Foundation.
	
	    This program is distributed in the hope that it will be useful,
	    but WITHOUT ANY WARRANTY; without even the implied warranty of
	    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	    GNU General Public License for more details.
	
	    You should have received a copy of the GNU General Public License
	    along with this program; if not, write to the Free Software
	    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
	*/
	
	
	
	if(!defined('ABSPATH')) {
		exit();
		
	} else if (is_admin()) {
		$sbsp = array(
			'cacheKey'		=> 'sbsp_select',
			'keyCreated'	=> FALSE
		);
		
		
		/* Hooks */
		/* ----- */
		
		add_action('restrict_manage_posts', 'sbsp_createParentDropDown');	// Create dropdown.
		add_action('save_post', 'sbsp_removeCacheKey');						// To delete cache entry on save.
		add_action('pre_get_posts', 'sbsp_applyFilter');					// Uses the actual filter to affect the query.
		
		
		
		/* Functions */
		/* --------- */
		
		/**
		 * Creates a filter for table listings of hierarchical posts so that you can view just subposts of a specific post.
		 *
		 * @since 0.1.0
		 * @access public
		 * @param object $query A REFERENCE to the query object.
		 * @return void
		 */
		function sbsp_applyFilter($query) {
			if ($query->is_main_query() && !$query->is_single() && is_post_type_hierarchical($query->query_vars['post_type'])) {
				$parentId = sbsp_getParentId();
				
				if ($parentId > 0) $query->set('post_parent__in', array($parentId));
			}
		}
		
		/**
		 * Creates a filter for table listings of hierarchical posts so that you can view just subposts of a specific post.
		 *
		 * @since 0.1.0
		 * @access public
		 * @return integer The value of the parent or -1.
		 */
		function sbsp_getParentId() {
			if(!empty($_GET) && !empty($_GET['sbsp_subposts_filter'])) return intval($_GET['sbsp_subposts_filter']);
			
			return -1;
		}
		
		/**
		 * Creates a filter for table listings of hierarchical posts so that you can view just subposts of a specific post.
		 *
		 * @since 0.1.0
		 * @access public
		 * @return void
		 */
		function sbsp_createParentDropDown() {
			$screen = get_current_screen();
			
				// We are only interested in hierarchical post types.
			if (is_post_type_hierarchical($screen->post_type)) {
				$output		= get_transient(sbsp_getCacheKey());
				$parentId	= sbsp_getParentId();
				
				if (empty($output)) {
					$topLevelPosts = get_posts(array(
						'posts_per_page'	=> -1,
						'post_parent'		=> 0,
						'post_type'			=> $screen->post_type,
						'suppress_filters'	=> false // Allows plugins like WPML to apply their filters.
					));
					
					$finalPosts	= array();
					
					if (count($topLevelPosts) > 0) $finalPosts = sbsp_processPosts($topLevelPosts, $screen->post_type, $finalPosts);
					
						// Output selector:
					if (!empty($finalPosts)) {
						$output	.=	'<select name="sbsp_subposts_filter">';
						$output	.=		'<option value="-1">###DUMMY_OPTION###</option>';
						$output .= 		sbsp_createOptions($finalPosts);
						$output .=	'</select>';
					}
					
						// Set even if there are none so we don't try checking pages all the time.
						// The page save hook will clear the cache as needed, or the cache will expire after 4 weeks.
						// NB: No option is selected in this cache entry, that gets done in sbsp_selectOption() so that
						// the same cache entry can be used for all subpages.
					set_transient(sbsp_getCacheKey(), $output, WEEK_IN_SECONDS * 4);
				}
				
					// Add dummy label
				$output = str_replace('###DUMMY_OPTION###', __('Subpages of:', 'sbs'), $output);
				
					// Now echo output after marking selected option.
				echo sbsp_selectOption($output, $parentId);
			}
		}
		
		/**
		 * Returns the correct cache key.
		 *
		 * @since 0.1.0
		 * @access public
		 * @return string The cahce key.
		 */
		function sbsp_getCacheKey() {
			global $sbsp;
			
			if ($sbsp['keyCreated'] === FALSE) {
				$screen				= get_current_screen();
				$sbsp['cacheKey']  .= '_' . $screen->post_type;
				$sbsp['keyCreated']	= TRUE;
			}
			
			return $sbsp['cacheKey'];
		}
		
		/**
		 * Called on post update. Deletes any cache entry saved.
		 *
		 * @since 0.1.0
		 * @access public
		 */
		function sbsp_removeCacheKey() {
			$screen = get_current_screen();
			setcookie("TestCookie", sbsp_getCacheKey());
				// We are only interested in hierarchical post types.
			if (is_post_type_hierarchical($screen->post_type)) delete_transient(sbsp_getCacheKey());
		}
		
		/**
		 * A recursive function that creates options for a select tag.
		 *
		 * @since 0.1.0
		 * @access public
		 * @param array $options An array of select options.
		 * @return string $optStr The new options.
		 */
		function sbsp_createOptions(array $options) {
			$optStr = '';
			
			foreach($options as $opt) {
				$optDepth	= ($opt['depth'] > 0) ? str_repeat('&nbsp;&nbsp;&nbsp;', $opt['depth']) . ' ' : '';
				$optStr .= '<option data-depth="' . $opt['depth'] . '" value="' . $opt['id'] . '">' . $optDepth . $opt['title'] . '</option>';
				
				if ($opt['childCount'] > 0) $optStr .= sbsp_createOptions($opt['children']);
			}
			
			return $optStr;
		}
		
		/**
		 * Marks the correct option as selected in a html string.
		 *
		 * @since 0.1.0
		 * @access public
		 * @param string $optionsHtml The html to look for the 'value="$id"'.
		 * @param integer $id The id to mark as selected.
		 * @return string $optionsHtml The html, modified if a match is found.
		 */
		function sbsp_selectOption($optionsHtml, $id) {
			if ($id > 0) {
				$searchStr = 'value="' . $id . '"';
				$searchPos = strpos($optionsHtml, $searchStr);
				
				if ($searchPos !== FALSE) $optionsHtml = str_replace($searchStr, $searchStr . ' selected="selected"', $optionsHtml);
			}
			
			return $optionsHtml;
		}
		
		/**
		 * A recursive function that returns an array of information about a hierarchical post type an its children.
		 *
		 * @since 0.1.0
		 * @access public
		 * @param array $posts An array of post objects.
		 * @param string $postType The type of posts in $posts.
		 * @param array $parentArray The current array being processed post info should be added.
		 * @param integer $depth The current depth.
		 * @return array $parentArray The parent array with child data, if any.
		 */
		function sbsp_processPosts(array $posts, $postType, array $parentArray, $depth = 0) {
			foreach($posts as $post) {
				$childPosts = get_posts(array(
					'posts_per_page'	=> -1,
					'post_parent'		=> $post->ID,
					'post_type'			=> $postType,
					'suppress_filters'	=> false // Allows plugins like WPML to apply their filters.
				));
				
				$newItem = array(
					'id'			=> $post->ID,
					'title'			=> $post->post_title,
					'depth'			=> $depth,
					'childCount'	=> count($childPosts),
					'children'		=> array()
				);
				
				if ($newItem['childCount'] > 0) {
					$newItem['children']	= sbsp_processPosts($childPosts, $postType, $newItem['children'], $depth + 1);
					$parentArray[]			= $newItem;
				}
			}
			
			return $parentArray;
		}
		
	} // Else FE, nothing to do...
?>
