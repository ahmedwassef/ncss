<?php

namespace Drupal\ncss_about_block\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for text cleaning functions.
 */
class TextCleaner extends AbstractExtension
{

  /**
   * {@inheritdoc}
   */
  public function getName()
  {
    return 'text_cleaner';
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters()
  {
    return [
      new TwigFilter('remove_duplicate_dashes', [$this, 'removeDuplicateDashes']),
      new TwigFilter('content_type_display_name', [$this, 'getContentTypeDisplayName']),
    ];
  }

  /**
   * Removes duplicate dashes from text.
   *
   * @param string $text
   *   The input text.
   *
   * @return string
   *   The text with duplicate dashes removed.
   */
  public function removeDuplicateDashes($text)
  {
    if (empty($text)) {
      return $text;
    }

    // Replace multiple consecutive dashes with a single dash
    $cleaned = preg_replace('/-+/', '-', $text);

    return $cleaned;
  }

  /**
   * Gets the display name for content type based on ID.
   *
   * @param int $content_type_id
   *   The content type ID.
   *
   * @return string
   *   The display name for the content type.
   */
  public function getContentTypeDisplayName($content_type_id)
  {
    $display_names = [
      1 => 'الدليل التنظيمي',
      2 => 'دليـل السـيـاسات والإجراءات للجامعة',
    ];

    return $display_names[$content_type_id] ?? '';
  }

}
