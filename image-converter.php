<?php
function preprocess_uploaded_image($file) {
    // First, check if the GD Library is available
    if (!extension_loaded('gd') || !function_exists('gd_info')) {
        error_log("GD Library not available for image processing.");
        return $file;
    }

    $uploaded_file_tmp_name = $file['tmp_name'];
    $uploaded_file_type = $file['type'];
    $uploaded_file_name = $file['name'];

    // Only process supported image types
    if (strpos($uploaded_file_type, 'image') === false) {
        return $file;
    }

    // Only process supported image file formats, that is not webp
    $extension = strtolower(pathinfo($uploaded_file_name, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpeg', 'jpg', 'png', 'gif'])) {
        return $file;
    }

    // Normalize the extension for PHP functions
    if ($extension === 'jpg') $extension = 'jpeg';

    // Create an image identifier representing the image obtained from the given filename. 
    $create_function = "imagecreatefrom{$extension}";
    $source_image = @$create_function($uploaded_file_tmp_name);
    if (!$source_image) {
        error_log("Failed to create image from uploaded file using GD.");
        return $file;
    }

    // Resize image
    $max_dimension = 1920;
    $orig_width = imagesx($source_image);
    $orig_height = imagesy($source_image);
    if ($orig_width > $max_dimension || $orig_height > $max_dimension) {
        $ratio = $max_dimension / max($orig_width, $orig_height);
        $new_width = intval($orig_width * $ratio);
        $new_height = intval($orig_height * $ratio);

        // Create a new blank image with the calculated dimensions.
        $resized_image = imagecreatetruecolor($new_width, $new_height);
        // Resample the original image onto the resized image, effectively resizing it.
        if(!imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height)) {
          error_log('Failed to resample the image.');
          imagedestroy($resized_image);
          imagedestroy($source_image);
          return $file;
        }
    
        imagedestroy($source_image);
        $source_image = $resized_image;
    }

    // Convert image to WebP, squashing the previous uploaded tmp file.
    if (!imagewebp($source_image, $uploaded_file_tmp_name, 80)) {
        error_log("Failed to convert uploaded image to WebP.");
        imagedestroy($source_image);
        return $file;
    }

    imagedestroy($source_image);

    $file['tmp_name'] = $uploaded_file_tmp_name; // uploaded file is squash
    $file['name'] = wp_basename($uploaded_file_name, ".$extension") . '.webp';
    $file['type'] = 'image/webp';
    $file['size'] = filesize($uploaded_file_tmp_name);

    return $file;
}
add_filter('wp_handle_upload_prefilter', 'preprocess_uploaded_image');
