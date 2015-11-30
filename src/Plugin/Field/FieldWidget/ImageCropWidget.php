<?php

/**
 * @file
 * Contains \Drupal\imagefield_crop\Plugin\Field\FieldWidget\ImageCropWidget.
 */
namespace Drupal\imagefield_crop\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;
use Drupal\Component\Utility\Html;

/**
 * Plugin implementation of the 'image_image_crop' widget.
 *
 * @FieldWidget(
 *   id = "image_image_crop",
 *   label = @Translation("Image Crop"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageCropWidget extends ImageWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'progress_indicator' => 'throbber',
      'preview_image_style' => 'thumbnail',
      'collapsible' => 2,
      'resolution' => '200x150',
      'enforce_ratio' => TRUE,
      'enforce_minimum' => TRUE,
      'croparea' => '500x500',
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['collapsible'] = array(
      '#type' => 'radios',
      '#title' => t('Collapsible behavior'),
      '#options' => array(
        1 => t('None.'),
        2 => t('Collapsible, expanded by default.'),
        3 => t('Collapsible, collapsed by default.'),
      ),
      '#default_value' => $this->getSetting('collapsible'),
    );

    // Resolution settings.
    $resolution = $this->getSetting('resolution');
    list($res_w, $res_h) = explode('x', $resolution);
    $element['resolution'] = array(
      '#title' => t('The resolution to crop the image onto'),
      '#element_validate' => array(
        array('Drupal\image\Plugin\Field\FieldType\ImageItem', 'validateResolution'),
        array(get_class($this), 'validateResolution'),
      ),
      '#theme_wrappers' => array('form_element'),
      '#description' => t('The output resolution of the cropped image, expressed as WIDTHxHEIGHT (e.g. 640x480). Set to 0 not to rescale after cropping. Note: output resolution must be defined in order to present a dynamic preview.'),
    );
    $element['resolution']['x'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($res_w) ? $res_w : '',
      '#size' => 5,
      '#maxlength' => 5,
      '#field_suffix' => ' x ',
      '#theme_wrappers' => array(),
    );
    $element['resolution']['y'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($res_h) ? $res_h : '',
      '#size' => 5,
      '#maxlength' => 5,
      '#field_suffix' => ' ' . t('pixels'),
      '#theme_wrappers' => array(),
    );
    $element['enforce_ratio'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enforce crop box ratio'),
      '#default_value' => $this->getSetting('enforce_ratio'),
      '#description' => t('Check this to force the ratio of the output on the crop box. NOTE: If you leave this unchecked but enforce an output resolution, the final image might be distorted'),
      '#element_validate' => array(
        array(get_class($this), 'validateEnforceRatio'),
      ),
    );
    $element['enforce_minimum'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enforce minimum crop size based on the output size'),
      '#default_value' => $this->getSetting('enforce_minimum'),
      '#description' => t('Check this to force a minimum cropping selection equal to the output size. NOTE: If you leave this unchecked you might get zoomed pixels if the cropping area is smaller than the output resolution.'),
      '#element_validate' => array(
        array(get_class($this), 'validateEnforceMinimum'),
      ),
    );

    // Crop area settings
    $croparea = $this->getSetting('croparea');
    list($crop_w, $crop_h) = explode('x', $croparea);
    $element['croparea'] = array(
      '#title' => t('The resolution of the cropping area'),
      '#element_validate' => array(
        array(get_class($this), 'validateCropArea'),
      ),
      '#theme_wrappers' => array('form_element'),
      '#description' => t('The resolution of the area used for the cropping of the image. Image will displayed at this resolution for cropping. Use WIDTHxHEIGHT format, empty or zero values are permitted, e.g. 500x will limit crop box to 500 pixels width.'),
    );
    $element['croparea']['x'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($crop_w) ? $crop_w : '',
      '#size' => 5,
      '#maxlength' => 5,
      '#field_suffix' => ' x ',
      '#theme_wrappers' => array(),
    );
    $element['croparea']['y'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($crop_h) ? $crop_h : '',
      '#size' => 5,
      '#maxlength' => 5,
      '#field_suffix' => ' ' . t('pixels'),
      '#theme_wrappers' => array(),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    // Insert our own summary here.

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#imagefield_crop_resolution'] = $this->getSetting('resolution');
    $element['#imagefield_crop_croparea'] = $this->getSetting('croparea');
    $element['#imagefield_crop_enforce_ratio'] = $this->getSetting('enforce_ratio');
    $element['#imagefield_crop_enforce_minimum'] = $this->getSetting('enforce_minimum');
    return $element;
  }

  /**
   * Form API callback: Processes a image_image field element.
   *
   * Expands the image_image type to include the alt and title fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    $element['#description'] = t('Click on the image and drag to mark how the image will be cropped');

    $element['#theme'] = 'imagefield_crop_widget';

    $element['#attached']['library'][] = 'imagefield_crop/core';

    $cropbox_id = Html::getUniqueId('cropbox-image');

    $element['#id'] = $cropbox_id;

    // Add the image preview.
    if (!empty($element['#files']) && $element['#preview_image_style']) {
      $file = reset($element['#files']);
      $variables = array(
        'style_name' => $element['#preview_image_style'],
        'uri' => $file->getFileUri(),
      );

      // Determine image dimensions.
      if (isset($element['#value']['width']) && isset($element['#value']['height'])) {
        $variables['width'] = $element['#value']['width'];
        $variables['height'] = $element['#value']['height'];
      }
      else {
        $image = \Drupal::service('image.factory')->get($file->getFileUri());
        if ($image->isValid()) {
          $variables['width'] = $image->getWidth();
          $variables['height'] = $image->getHeight();
        }
        else {
          $variables['width'] = $variables['height'] = NULL;
        }
      }

      $element['preview']['#theme'] = 'imagefield_crop_preview';

      $element['cropbox'] = array(
        '#theme' => 'image',
        '#uri' => $file->getFileUri(),
        '#attributes' => array(
          'class' => 'cropbox',
          'id' => $cropbox_id . '-cropbox'
        ),
        '#description' => t('Click on the image and drag to mark how the image will be cropped.'),
      );

      $element['cropinfo'] = self::addCropInfoFields($file->get('fid')->getString());

      list($res_w, $res_h) = explode('x', $element['#imagefield_crop_resolution']);
      list($crop_w, $crop_h) = explode('x', $element['#imagefield_crop_croparea']);
      $settings = array(
        $cropbox_id => array(
          'box' => array(
            'ratio' => $res_h ? $element['#imagefield_crop_enforce_ratio'] * $res_w/$res_h : 0,
            'box_width' => $crop_w,
            'box_height' => $crop_h,
          ),
          'minimum' => array(
            'width'   => $element['#imagefield_crop_enforce_minimum'] ? $res_w : NULL,
            'height'  => $element['#imagefield_crop_enforce_minimum'] ? $res_h : NULL,
          ),
        ),
      );

      $element['#attached']['drupalSettings']['imagefield_crop'] = $settings;
    }

    return $element;
  }


  public static function addCropInfoFields($fid = NULL) {
    $element = array();
    $defaults = array(
      'x'       => 0,
      'y'       => 0,
      'width'   => 50,
      'height'  => 50,
      'changed' => 0,
    );

    if ($fid) {
      $file_info = imagefield_crop_get_file_info($fid);
      if ($file_info) {
        $defaults = array_merge($defaults, $file_info);
      }
    }

    foreach ($defaults as $name => $default) {
      $element[$name] = array(
        '#type' => 'hidden',
        '#title' => $name,
        '#attributes' => array('class' => array('edit-image-crop-' . $name)),
        '#default_value' => $default,
      );
    }

    return $element;
  }

  /**
   * Element validate function for resolution fields.
   */
  public static function validateResolution($element, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $settings = NestedArray::getValue($values, array_slice($element['#parents'], 0, -1));

    // Drupal\image\Plugin\Field\FieldType\ImageItem->validateResolution does most of the validation
    if ($settings['enforce_ratio'] && (empty($element['x']['#value']))) {
      $form_state->setError($element, t('Target resolution must be defined as WIDTHxHEIGHT if resolution is to be enforced'));
    }
  }

  /**
   * Element validate function for resolution fields.
   */
  public static function validateEnforceMinimum($element, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $settings = NestedArray::getValue($values, array_slice($element['#parents'], 0, -1));

    list($res_w, $res_h) = explode('x', $settings['resolution']);

    $rw = ($res_w) ? $res_w : 0;
    $rh = ($res_h) ? $res_h : 0;

    if ($settings['enforce_minimum'] && (!is_numeric($rw) || intval($rw) != $rw || $rw <= 0 || !is_numeric($rh) || intval($rh) != $rh || $rh <= 0)) {
      $form_state->setError($element, t('Target resolution must be defined as WIDTH_HEIGHT if minimum is to be enforced.'));
    }
  }

  /**
   * Element validate function for resolution fields.
   */
  public static function validateCropArea($element, FormStateInterface $form_state) {
    foreach (array('x', 'y') as $dimension) {
      $value = $element[$dimension]['#value'];
      if (!empty($value) && !is_numeric($value)) {
        $form_state->setError($element[$dimension], t('The @dimension value must be numeric.', array('@dimesion' => $dimension)));
        return;
      }
    }

    $form_state->setValueForElement($element, intval($element['x']['#value']) . 'x' . intval($element['y']['#value']));
  }

  /**
   * Element validate function for resolution fields.
   */
  public static function validateEnforceRatio($element, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $settings = NestedArray::getValue($values, array_slice($element['#parents'], 0, -1));

    list($res_w, $res_h) = explode('x', $settings['resolution']);

    if ($res_w && !$element['#value']) {
      drupal_set_message(t('Output resolution is defined, but not enforced. Final images might be distorted'), 'warning');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Don't do entity saving when we have validation erors.
    if (count($form_state->getErrors()) || !$form_state->isValidationComplete()) {
      return parent::massageFormValues($values, $form, $form_state);
    }

    foreach ($values as $value) {
      if (isset($value['fids'][0]) && isset($value['cropinfo']) && $value['cropinfo']['changed']) {
        $fid = $value['fids'][0];

        $new_crop_info = array(
          'fid' => $fid,
          'x' => $value['cropinfo']['x'],
          'y' => $value['cropinfo']['y'],
          'width' => $value['cropinfo']['width'],
          'height' => $value['cropinfo']['height'],
        );

        imagefield_crop_update_file_info($new_crop_info);
      }
    }

    return parent::massageFormValues($values, $form, $form_state);
  }
}


