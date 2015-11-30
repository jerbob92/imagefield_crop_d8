<?php

/**
 * @file
 * Contains \Drupal\imagefield_crop\Plugin\ImageEffect\FieldCropImageEffect.
 */

namespace Drupal\imagefield_crop\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\image\ImageEffectBase;

/**
 * Crops an image resource.
 *
 * @ImageEffect(
 *   id = "imagefield_crop",
 *   label = @Translation("Imagefield Crop"),
 *   description = @Translation("Will take the Imagefield Crop info and applies the cropping.")
 * )
 */
class FieldCropImageEffect extends ImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $path = $image->getSource();

    $entity_manager = \Drupal::entityManager();
    $files = $entity_manager->getStorage('file')->loadByProperties(array(
      'uri' => $path,
    ));
    $file = NULL;
    if (count($files)) {
      $file = reset($files);

      $info = imagefield_crop_get_file_info($file->id());

      if (!$info) {
        return TRUE;
      }

      if (!$image->crop($info['x'], $info['y'], $info['width'], $info['height'])) {
        $this->logger->error('Image crop failed using the %toolkit toolkit on %path (%mimetype, %dimensions)', array('%toolkit' => $image->getToolkitId(), '%path' => $image->getSource(), '%mimetype' => $image->getMimeType(), '%dimensions' => $image->getWidth() . 'x' . $image->getHeight()));
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {

    $entity_manager = \Drupal::entityManager();
    $files = $entity_manager->getStorage('file')->loadByProperties(array(
      'uri' => $uri,
    ));
    $file = NULL;
    if (count($files)) {
      $file = reset($files);

      $info = imagefield_crop_get_file_info($file->id());

      if ($info) {
        // The new image will have the exact dimensions defined for the effect.
        $dimensions['width'] = $info['width'];
        $dimensions['height'] = $info['height'];
      }
    }
  }
}
