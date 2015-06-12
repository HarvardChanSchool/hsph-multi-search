<?php

/*
Plugin Name: HSPH Mutlisite Search
Plugin URI: http://sph.harvard.edu
Description: Perform a string search on titles and content of all the posts and pages of a network
Version: 1.0.0
Author: GMolter/HSPH Web Team
Author URI: http://sph.harvard.edu
Network: true
*/

/**
 * Add a widget to the network dashboard.
 *
 */
function hsph_multi_search_add_dashboard_widgets() {

	wp_add_dashboard_widget(
        'hsph_multi_search_dashboard_widget',        // Widget slug.
        'Multisite Search',         				// Title.
        'hsph_multi_search_display_dashboard_widget' // Display function.
    );
        
}
add_action( 'wp_network_dashboard_setup', 'hsph_multi_search_add_dashboard_widgets' );

/**
 * Display the content of our Dashboard Widget.
 */
function hsph_multi_search_display_dashboard_widget() {

	// Display whatever it is you want to show.
	echo '<form role="search" method="post" class="hsph-multi-search-form" action="'.admin_url( 'admin-ajax.php' ).'">';
	echo '	<input type="hidden" name="hsph_wp_nounce" value="'.wp_create_nonce( "hsph_ajax_security" ).'">';
	echo '	<input type="hidden" name="action" value="hsph_multi_search">';
	echo '	<label>';
	echo '		<span class="screen-reader-text">'.__("Search network").'</span>';
	echo '		<input required="required" type="search" class="search-field" placeholder="'.esc_attr_x( 'Search …', 'placeholder' ).'" name="multisearch" title="'.esc_attr_x( 'Search for:', 'label' ).'" />';
	echo '	</label>';
	echo '	<input type="submit" class="search-submit" value="'.esc_attr_x( 'Search', 'submit button' ).'" />';
	echo '</form>';

}


/**
 * We add an ajax action to perform the search
 */
add_action( 'wp_ajax_hsph_multi_search', 'hsph_multi_search_ajax_search' );

function hsph_multi_search_ajax_search() {
	//we check that user is allowed to perform the search and that the ajax request look valid
	if(current_user_can('manage_network') && check_ajax_referer( 'hsph_ajax_security', 'hsph_wp_nounce',false)){
	
		global $wpdb;
		
		//We store the initial blogID value for reseting at the end
		$initialBlogID = $wpdb->blogid;
		
		//Get the list of public blogs
		$blogs = wp_get_sites(array(
			"network_id" 	=> $wpdb->siteid,
			"public" 		=> 1,
			"archived" 		=> null,
			"deleted" 		=> null,
			"limit" 		=> 10000 
		));

		//Settings headers to return a file
		header("Content-Type: plain/text"); 
		header("Content-Disposition: Attachment; filename=hsph-web-multi-search-".date("Y-m-d-H-i-s").".txt"); 
		header("Pragma: no-cache");
		
		//vars to keep track of the number of occurences and blogs
		$i = 0;
		$j = 0;
		
		$multisearch = sanitize_text_field($_POST["multisearch"]);
		
		echo "#########################################################################"."\n";
		echo "Searching for occurences of: \"".$multisearch."\"\n";
		echo "#########################################################################"."\n\n";
		
		//for each existing public blog
		foreach ($blogs as $blog){
			
			$wpdb->set_blog_id($blog["blog_id"]);
			
			$wpdb->set_prefix($wpdb->base_prefix);
			
			//we get the list of posts containing our search string
			$query = 	$wpdb->prepare( " SELECT ID, post_title
											FROM $wpdb->posts
											WHERE post_status='publish' 
												AND ( post_title LIKE %s OR post_content LIKE %s ) 
												AND post_type IN ('post','page','research_project','multimedia-article','press-release','magazine','hsph-in-the-news','featured-news-story')"
										,"%".$multisearch."%","%".$multisearch."%");
												
											
			$results = $wpdb->get_results( $query, ARRAY_A );
			
			//we display each result
			if(is_array($results) && !empty($results)){
				
					$j++;

					echo "#########################################################################"."\n";
					echo "http://".$blog["domain"].$blog["path"]." - Blog ID: ".$blog["blog_id"]."\n";
					echo "#########################################################################"."\n\n";
					
					foreach ($results as $result){
						echo "\"".$result["post_title"]."\" (ID: ".$result["ID"].") - http://".$blog["domain"].$blog["path"]."?p=".$result["ID"]."\n\r";
						
						$i++;
					}
				
			}
						
		}
		
		//Displaying a few stats because who doesn't like stats?!
		echo "#########################################################################"."\n";
		echo $i ." occurrence";
		if($i>1) {
			echo "s ";
		}
		
		if($i>1){
			echo "found on ".$j." blog";
			if($j>1){
				echo "s ";
			}

		}
		else{
			echo "found";
		}
		
		echo "\n";
		
		echo "#########################################################################"."\n\n";
				
		// If the initial blogId has been changed we reset it
		if($wpdb->blogid != $initialBlogID){
			$wpdb->set_blog_id($initialBlogID);
			$wpdb->set_prefix($wpdb->base_prefix);
		}
		
	}
	
	wp_die(); // this is required to terminate immediately and return a proper response
}
	
?>