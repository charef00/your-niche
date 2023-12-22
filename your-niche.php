<?php
/**
 * Your Niche
 *
 * Plugin Name: Your Niche
 * Plugin URI:  https://github.com/charef00/your-niche.git
 * Description: The Your-Niche Post Creator plugin enables users to add new posts to their WordPress site directly from Your-Niche.com via a secure API. This plugin simplifies content creation by allowing seamless post integration from the Your-Niche "create content with AI in Image AI" platform. Users can authenticate with Your-Niche.com and then select content to be automatically posted on their WordPress site.
 * Version: 1.0
 * Author:      Charef Ayoub
 * Author URI:  https://www.linkedin.com/in/ayoub-charef-531897128/
 * License:     GPLv2 or later
 * License URI: 
 * Text Domain: your-niche.com
 * Domain Path: /languages
 * Requires at least: 4.9
 * Requires PHP: 5.2.4
 *
 * This program is free software; 
 */


 defined('ABSPATH') or die('Direct script access disallowed.');



 add_action('rest_api_init', function () {
     // Route for custom functionality
     register_rest_route('your-niche', '/check/', array(
        'methods' => 'GET',
        'callback' => 'my_check_function',
        'args' => array(
            'email' => array(
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_email($param);
                }
            ),
        ),
    ));
    register_rest_route('your-niche', '/check-post/', array(
         'methods' => 'GET',
         'callback' => 'check_post_by_title',
         'args' => array(
             'title' => array(
                 'required' => true,
                 'validate_callback' => function($param, $request, $key) {
                     return is_string($param);
                 }
             ),
         ),
     ));
     // Route for fetching categories
     register_rest_route('your-niche', '/categories/', array(
         'methods' => 'GET',
         'callback' => 'get_all_categories'
     ));
 
     // Route for adding a new post
     register_rest_route('your-niche', '/add-post/', array(
         'methods' => 'POST',
         'callback' => 'add_new_post',
         'permission_callback' => function (WP_REST_Request $request) {
             // Retrieve the email from request parameters
             $admin_email = $request->get_param('admin_email');
     
             // Get user data by email
             $user = get_user_by('email', $admin_email);
     
             // Check if user exists and is an admin
             return ($user && in_array('administrator', $user->roles));
         }
     ));
 });
 
 function my_check_function($request) {
    // Retrieve the email from the request
    $email = $request->get_param('email');

    // Get user data by email
    $user = get_user_by('email', $email);

    // Check if user exists and has admin capability
    if ($user && in_array('administrator', $user->roles)) {
        // User is an admin
        return new WP_REST_Response(true, 200);
    } else {
        // User is not an admin or doesn't exist
        return new WP_REST_Response(false, 200);
    }
}
 
 function check_post_by_title($request) {
     $title = $request->get_param('title');
     $args = array(
         'post_type'      => 'post',
         'post_status'    => 'publish',
         'name'          => $title,
         'posts_per_page' => -1  // Retrieve all posts with the given title
     );
 
     $posts = get_posts($args);
 
     if (!empty($posts)) {
         return new WP_REST_Response(count($posts), 200);  // Return the count of posts
     } else {
         return new WP_REST_Response(0, 200);  // Return 0 if no posts are found
     }
 }
 
 function get_all_categories($request) {
     $args = array(
         'hide_empty' => false,
     );
 
     $categories = get_categories($args);
     $simplified_categories = array();
 
     foreach ($categories as $category) {
         $simplified_categories[] = array(
             'id' => $category->term_id,
             'name' => $category->name
         );
     }
 
     return new WP_REST_Response($simplified_categories, 200);
 }
 
 
 function add_new_post(WP_REST_Request $request) 
 {
     
     // Extract parameters from the request
     $title = sanitize_text_field($request->get_param('title'));
     $tests=$request->get_param('post_content');
     $images=$request->get_param('image_content');
     $logo=esc_url_raw($request->get_param('logo'));
     //$content = sanitize_textarea_field($request->get_param('content'));
 
     $content="";
     $image_source = esc_url_raw($request->get_param('image_source'));
     if (is_array($images) && !empty($images)) { 
     
         for ($i = 0; $i < count($images); $i++) 
         {
             $src=esc_url_raw($images[$i]);
             $image_source =$src;
             $result = downloadImgWithApi($src, $title, $i,$logo);
             $content = $content . $tests[$i] . "<img src='" . $result . "' style='width:100%;max-width:500px;'>";
         }
     }
     $content=$content.$tests[count($tests)-1];
     // Create the post array
     $post_data = array(
         'post_title'   => $title,
         'post_content' => $content,
         'post_status'  => 'publish',
         'post_type'    => 'post',
     );
     // Insert the post
     $post_id = wp_insert_post($post_data);
     // Download and attach the image to the post
     if (!empty($image_source)) {
         $attachment_id = download_and_attach_image($image_source,$title, $post_id,$logo);
     }
    $category_ids = $request->get_param('categories'); 
    // Check if category_ids is an array and not empty
    if (is_array($category_ids) && !empty($category_ids)) {
        // Sanitize each category ID
        $sanitized_category_ids = array_map('absint', $category_ids);

        // Set categories for the post
        wp_set_post_terms($post_id, $sanitized_category_ids, 'category');
    }
    return new WP_REST_Response(
    array(
        'message' => 'Post created successfully.',
        'post_id' => $post_id,
        'attachment_id' => $attachment_id ?? null
    ), 
    200
    );
 }
 
 function download_and_attach_image($image_url,$title,$post_id,$logo) 
 {
     require_once(ABSPATH . 'wp-admin/includes/image.php');
     require_once(ABSPATH . 'wp-admin/includes/file.php');
     require_once(ABSPATH . 'wp-admin/includes/media.php');
 
     // Flask API URL
     $flask_api_url = "http://mirror.read-book.org/api";

     // Data to send to the Flask API
     $data = array(
         'url'  => $image_url,
         'logo' => $logo,
         'site' => site_url(),
         'name' => "img"
     );
 
     // Initialize cURL
     $curl = curl_init();
 
     // Set cURL options
     curl_setopt($curl, CURLOPT_URL, $flask_api_url);
     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
     curl_setopt($curl, CURLOPT_POST, true);
     curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
 
     // Execute cURL session
     $processed_image = curl_exec($curl);
     curl_close($curl);
 
 
     // Check for errors
     if (!$processed_image) {
         return new WP_Error('image_processing_error', 'Error in processing image');
     }
 
     // Save the image to a temporary file
     $tmp_file = tmpfile();
     $tmp_file_path = stream_get_meta_data($tmp_file)['uri'];
     fwrite($tmp_file, $processed_image);
     
     // Prepare file array for media_handle_sideload
     $file_array = array(
         'name' => slugify($title).'.jpg', // You might want to generate a unique name
         'tmp_name' => $tmp_file_path
     );
 
     // Upload the image and get the attachment ID
     $attachment_id = media_handle_sideload($file_array, $post_id);
 
     // Close the temporary file
     fclose($tmp_file);
 
     // Set as featured image
     if (!is_wp_error($attachment_id)) {
         set_post_thumbnail($post_id, $attachment_id);
     }
 
     return $attachment_id;
 }
 
 
 
 function slugify($text, string $divider = '-')
 {
   // replace non letter or digits by divider
   $text = preg_replace('~[^\pL\d]+~u', $divider, $text);
 
   // transliterate
   $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
 
   // remove unwanted characters
   $text = preg_replace('~[^-\w]+~', '', $text);
 
   // trim
   $text = trim($text, $divider);
 
   // remove duplicate divider
   $text = preg_replace('~-+~', $divider, $text);
 
   // lowercase
   $text = strtolower($text);
 
   if (empty($text)) {
     return 'n-a';
   }
 
   return trim($text);
 }
 
 
 function clearData($txt)
 {
     $txt=str_replace('"', "“", $txt);
     $txt=str_replace("'", "\'", $txt);
     return $txt;
 }
 function downloadImgWithApi($image_url, $title, $step,$logo)
 {
     
     $data = array(
         'url'  => $image_url,
         'logo' => $logo,
         'site' => site_url(),
         'name' => "img"
     );
     $query=http_build_query($data);
     $options = array(
         'http' => array(
             'header' => "Content-Type: application/x-www-form-urlencoded\r\n".
                             "Content-Length: ".strlen($query)."\r\n".
                             "User-Agent:MyAgent/1.0\r\n",
             'method'  => "POST",
             'content' => $query,
             ),
         );
     $context = stream_context_create($options); 
     $url="https://mirror.read-book.org/api";
     $result = file_get_contents($url, false, $context);
     // Generate a unique temporary filename
     $upload_dir = wp_upload_dir();
     $tmp_file_name = slugify($title) . '_' . $step . '.jpg';
     $tmp_file_path = $upload_dir['path'] . '/' . $tmp_file_name;
     // Save the processed image to the temporary file
     file_put_contents($tmp_file_path, $result);
     return $upload_dir['url'] . '/' . $tmp_file_name;
 }
 ?>
 