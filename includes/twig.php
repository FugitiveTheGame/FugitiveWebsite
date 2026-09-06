<?php

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Shared Twig environment for the public pages, the stats page and the
 * management area. Templates live in ../templates, outside the docroot.
 */
function fugitiveTwig(): \Twig\Environment
{
	static $twig = null;

	if ($twig !== null)
	{
		return $twig;
	}

	// Compiled templates are cached outside the docroot. If the directory
	// cannot be created or written to, Twig compiles in memory instead: a
	// little slower, but the site still renders rather than 500ing.
	$cache = __DIR__ . '/../var/cache/twig';
	if (!is_dir($cache))
	{
		@mkdir($cache, 0775, true);
	}
	$usable = is_dir($cache) && is_writable($cache);

	$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
	$twig = new \Twig\Environment($loader, [
		'cache' => $usable ? $cache : false,
		'autoescape' => 'html',
		// Not optional. auto_reload defaults to the value of debug, i.e. false,
		// and Twig keys the cache on the template *name* rather than its
		// contents. Without this, an edited template keeps rendering from its
		// stale compiled class: the deploy succeeds and the page never changes.
		// The cost is one stat() per template per request.
		'auto_reload' => true,
	]);

	return $twig;
}

/**
 * Render a template straight to the response. Every public page is a
 * three-line file that calls this.
 */
function renderPage(string $template, array $context = []): void
{
	echo fugitiveTwig()->render($template, $context);
}
