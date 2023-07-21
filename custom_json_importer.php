<?php
/*
Plugin Name: Custom JSON Importer
Plugin URI: https://camthewebguy.com
Description: Custom importer for JSON data made for Burgundy Wave by CamTheWebGuy.
Version: 1.0
Author: CamTheWebGuy
Author URI: https://camthewebguy.com
*/

// Activate the plugin
function custom_json_importer_activate() {
    // Activation code here (if needed)
}
register_activation_hook( __FILE__, 'custom_json_importer_activate' );

// Deactivate the plugin
function custom_json_importer_deactivate() {
    // Deactivation code here (if needed)
}
register_deactivation_hook( __FILE__, 'custom_json_importer_deactivate' );



// Import JSON data from a given file as Posts
function custom_json_import( $json_file_path, $import_type ) {
    // Check if the file exists
    if ( ! file_exists( $json_file_path ) ) {
        echo '<div class="notice notice-error"><p>File not found. Please provide a valid JSON file.</p></div>';
        return;
    }

    // Read the JSON file
    $json_data = file_get_contents( $json_file_path );

    // Convert JSON to PHP array
    $entries = json_decode( $json_data, true );

    // Check if the JSON data was successfully decoded
    if ( is_array( $entries ) ) {
        foreach ( $entries as $entry ) {
            // Extract data from the JSON entry
            $author_name = $entry['authorProfile']['name'];
            $post_date = $entry['updatedAt'];
            $content_blocks = array();
            foreach ( $entry['body']['components'] as $component ) {
                if ( isset( $component['contents']['html'] ) ) {
                    $content_blocks[] = $component['contents']['html'];
                }
            }
            $content = implode( '<br><br>', $content_blocks ); // Add two line breaks between HTML blocks
            $title = $entry['title'];
            $url = $entry['url'];
            $slug = sanitize_title( str_replace( 'https://www.burgundywave.com/', '', $url ) );

            // Check if the URL contains '/pages/' and import type is set to 'posts'
            if ( $import_type === 'post' && strpos( $url, '/pages/' ) !== false ) {
                continue; // Skip this entry and move to the next one
            }

            // Replace each single <br> with a double <br>
            $content = str_replace( '<br>', '<br><br>', $content );

            // Check if the author exists
            $user = get_user_by( 'login', $author_name );
            if ( ! $user ) {
                // If the author doesn't exist, create a new user
                $user_data = array(
                    'user_login' => $author_name,
                    'user_pass'  => null, // Set to null to generate a random password
                    'user_nicename' => $author_name,
                    'display_name' => $author_name,
                );
                $user_id = wp_insert_user( $user_data );

                // Check if user creation was successful
                if ( is_wp_error( $user_id ) ) {
                    echo '<div class="notice notice-error"><p>Error creating author. Please check the author name and try again.</p></div>';
                    continue; // Skip this entry and move to the next one
                }
            } else {
                // Use the existing user ID
                $user_id = $user->ID;
            }

            // Choose the post type based on the import type option
            $post_type = ( $import_type === 'page' ) ? 'page' : 'post';

            // Create post data
            $post_data = array(
                'post_title'   => $title,
                'post_content' => $content,
                'post_date'    => $post_date,
                'post_author'  => $user_id,
                'post_status'  => 'publish',
                'post_name'    => $slug,
                'post_type'    => $post_type,
            );

            // Insert the post into WordPress
            $post_id = wp_insert_post( $post_data );

            // Save additional meta data as needed
            // Example: Save the author name as post meta
            update_post_meta( $post_id, 'author_name', $author_name );

            // Add more logic to handle taxonomies, post relationships, etc.

        }
    }
}


// Import JSON data from a given file as Pages
function custom_json_import_pages( $json_file_path ) {
    // Read the JSON data from the file
    $json_data = file_get_contents( $json_file_path );

    // Check if the JSON data is valid
    $pages_data = json_decode( $json_data, true );

    if ( ! is_array( $pages_data ) ) {
        echo '<div class="notice notice-error"><p>Invalid JSON data. Please upload a valid JSON file.</p></div>';
        return;
    }

    // Loop through the pages data and import each page
    foreach ( $pages_data as $page ) {
        // Check if the URL contains "/pages/"
        if ( strpos( $page['url'], '/pages/' ) === false ) {
            // Skip this page as it doesn't match the criteria
            continue;
        }

        // Get the page content from the value of "rawHtml" inside "Body" > "Components"
        $page_content = '';
        if ( isset( $page['body']['components'] ) && is_array( $page['body']['components'] ) ) {
            foreach ( $page['body']['components'] as $component ) {
                if ( isset( $component['__typename'] ) && $component['__typename'] === 'EntryBodyHTML' ) {
                    $page_content .= $component['rawHtml'];
                }
            }
        }

        // Check if the author exists, create it if not
        $author_id = 1; // Default author ID, replace with the desired fallback author ID
        if ( isset( $page['author']['fullOrUserName'] ) ) {
            $author_data = get_user_by( 'login', $page['author']['fullOrUserName'] );
            if ( ! $author_data ) {
                // Author doesn't exist, create it
                $new_author = array(
                    'user_login' => $page['author']['fullOrUserName'],
                    'user_nicename' => sanitize_title( $page['author']['fullOrUserName'] ),
                    'display_name' => $page['author']['fullOrUserName'],
                    'role' => 'author', // Change the role if needed
                );

                $author_id = wp_insert_user( $new_author );
            } else {
                $author_id = $author_data->ID;
            }
        }

        // Create the page
        $new_page = array(
            'post_title' => $page['title'],
            'post_content' => $page_content,
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_author' => $author_id,
            'post_date' => date( 'Y-m-d H:i:s', strtotime( $page['createdAt'] ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( $page['updatedAt'] ) ),
        );

        // Insert the page into the database
        $page_id = wp_insert_post( $new_page );

        if ( $page_id ) {
            // Optionally, you can update some post meta values if needed
            // update_post_meta( $page_id, 'meta_key', 'meta_value' );

            // Optionally, you can set categories or tags for the page
            // wp_set_post_categories( $page_id, array( 'category_id' ) );
            // wp_set_post_tags( $page_id, 'tag1, tag2, tag3' );

            // Output a success message for each imported page
            // echo '<div class="notice notice-success"><p>Page "' . $page['title'] . '" imported successfully!</p></div>';
        } else {
            // Output an error message if the page couldn't be imported
            echo '<div class="notice notice-error"><p>Error importing the page "' . $page['title'] . '". Please try again.</p></div>';
        }
    }
}


// Add a submenu page under the "Tools" menu
function custom_json_importer_admin_page() {
    add_submenu_page(
        'tools.php',
        'Custom JSON Importer',
        'Custom JSON Importer',
        'manage_options',
        'custom-json-importer',
        'custom_json_importer_admin_page_callback'
    );
}
add_action( 'admin_menu', 'custom_json_importer_admin_page' );

// Callback function for the admin page
function custom_json_importer_admin_page_callback() {
    // Check if the user has the necessary permission
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Define the import types to run
    $import_types = array( 'post', 'page' );

    // Handle file upload if a file is submitted
    if ( isset( $_FILES['json_file'] ) ) {
        $uploaded_file = $_FILES['json_file'];

        // Check for file upload errors
        if ( $uploaded_file['error'] !== UPLOAD_ERR_OK ) {
            echo '<div class="notice notice-error"><p>Error uploading the file. Please try again.</p></div>';
            return;
        }

        // Check if it's a valid JSON file
        if ( $uploaded_file['type'] !== 'application/json' ) {
            echo '<div class="notice notice-error"><p>Invalid file format. Please upload a valid JSON file.</p></div>';
            return;
        }

        // Move the uploaded file to the plugin directory
        $upload_dir = plugin_dir_path( __FILE__ );
        $json_file_path = $upload_dir . $uploaded_file['name'];

        if ( move_uploaded_file( $uploaded_file['tmp_name'], $json_file_path ) ) {
            // Loop through each import type and run the import function
            foreach ( $import_types as $import_type ) {
                if ( $import_type === 'post' || $import_type === 'page' ) {
                    if ( $import_type === 'post' ) {
                        // echo '<h2>Running Import as "Post"...</h2>';
                        custom_json_import( $json_file_path, $import_type );
                    } elseif ( $import_type === 'page' ) {
                        // echo '<h2>Running Import as "Page"...</h2>';
                        custom_json_import_pages( $json_file_path );
                    }
                } else {
                    echo '<div class="notice notice-error"><p>Invalid import type specified. Please check the import types and try again.</p></div>';
                }
            }

            echo '<div class="notice notice-success"><p>Data imported successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error moving the file. Please try again.</p></div>';
        }
    }
    ?>

    <div class="wrap">
        <h1>Custom JSON Importer</h1>
        <form method="post" enctype="multipart/form-data">
            <p>Upload a JSON file to import data:</p>
            <input type="file" name="json_file" accept=".json">
            <!-- The import type selection is removed from the form -->
            <br><br>
            <input type="submit" class="button button-primary" value="Import">
        </form>
    </div>
    <?php
}
