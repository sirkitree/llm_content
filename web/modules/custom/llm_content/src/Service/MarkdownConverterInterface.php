<?php

declare(strict_types=1);

namespace Drupal\llm_content\Service;

use Drupal\node\NodeInterface;

/**
 * Interface for the markdown converter service.
 */
interface MarkdownConverterInterface {

  /**
   * Converts a node to markdown and stores the result.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to convert.
   *
   * @return string
   *   The generated markdown.
   */
  public function convert(NodeInterface $node): string;

  /**
   * Gets stored markdown for a node, generating if needed.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get markdown for.
   *
   * @return string
   *   The markdown content.
   */
  public function getMarkdown(NodeInterface $node): string;

  /**
   * Deletes stored markdown for a node.
   *
   * @param int $nid
   *   The node ID.
   */
  public function deleteMarkdown(int $nid): void;

}
