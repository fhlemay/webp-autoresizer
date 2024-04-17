<?php
// Works with wp-cli
function convert_and_resize_image_on_upload($attachment_id)
{
  $file = get_attached_file($attachment_id);
  $mime_type = get_post_mime_type($attachment_id);

  // Check if GD Library is available and MIME type is acceptable
  if (!function_exists('imagewebp') || strpos($mime_type, 'image/') === false) {
    return;
  }

  $info = getimagesize($file);
  $extension = image_type_to_extension($info[2], false);

  if (in_array($extension, ['jpeg', 'png', 'gif'])) {
    $createFunction = "imagecreatefrom{$extension}";
    if (function_exists($createFunction)) {
      $source = $createFunction($file);
      if (!$source) return; // Exit if image creation fails

      // Get original dimensions
      $orig_width = imagesx($source);
      $orig_height = imagesy($source);

      // Set maximum dimensions
      $max_dimension = 1920;

      // Compression quality
      $quality = 80;

      // Resize if necessary
      if ($orig_width > $max_dimension || $orig_height > $max_dimension) {
        $ratio = min($max_dimension / $orig_width, $max_dimension / $orig_height);
        $width = intval($orig_width * $ratio);
        $height = intval($orig_height * $ratio);
        $resized = imagecreatetruecolor($width, $height);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, $orig_width, $orig_height);
        imagedestroy($source); // Destroy original source image
        $source = $resized; // Set resized image as new source
      }

      $output = str_replace('.' . $extension, '.webp', $file);
      // Convert and save the image
      if (imagewebp($source, $output, $quality)) {
        // Free up memory
        imagedestroy($source);

        // Update attachment metadata and file path
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $output));
        update_attached_file($attachment_id, $output);

        // Delete the original file after successful conversion
        if (file_exists($file)) {
          @unlink($file); // force deletion
        }
      } else {
        // Free up memory if conversion fails
        imagedestroy($source);
      }
    }
  }
}
add_action('add_attachment', 'convert_and_resize_image_on_upload');
add_action('edit_attachment', 'convert_and_resize_image_on_upload');
