<?php

declare(strict_types=1);

namespace Drupal\llm_content\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Path processor for .md clean URLs.
 *
 * Inbound:  /{alias}.md  -> /node/{nid}/llm-md
 * Outbound: /node/{nid}/llm-md -> /{alias}.md.
 */
final class LlmMarkdownPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  public function __construct(
    protected readonly AliasManagerInterface $aliasManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request): string {
    if (!str_ends_with($path, '.md')) {
      return $path;
    }

    // Strip the .md suffix to get a potential alias.
    $alias = substr($path, 0, -3);

    // Ignore bare "/.md".
    if ($alias === '' || $alias === '/') {
      return $path;
    }

    // Resolve the alias to a system path.
    $systemPath = $this->aliasManager->getPathByAlias($alias);

    // If no alias found (returns unchanged) or not a node path, pass through.
    if ($systemPath === $alias || !preg_match('#^/node/\d+$#', $systemPath)) {
      return $path;
    }

    return $systemPath . '/llm-md';
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    if (!preg_match('#^/node/\d+/llm-md$#', $path)) {
      return $path;
    }

    // Strip '/llm-md'.
    $nodePath = substr($path, 0, -7);
    $alias = $this->aliasManager->getAliasByPath($nodePath);

    if ($bubbleable_metadata) {
      $bubbleable_metadata->addCacheTags(['path_alias_list']);
    }

    // If an alias exists (different from system path), return clean URL.
    if ($alias !== $nodePath) {
      return $alias . '.md';
    }

    return $path;
  }

}
