<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Wisymfony\SlugHistoryBundle\Service\Manager\SlugManager;

#[AsEventListener(event : KernelEvents::EXCEPTION, priority: 10)]
/**
 * Listener for kernel exceptions that attempts to redirect old slugs.
 *
 * When a `NotFoundHttpException` is thrown, this listener queries `SlugManager`
 * to resolve the requested path to a new path and issues a 301 redirect when
 * a mapping exists.
 */
class ExceptionRedirectListener
{
    /**
     * Constructor.
     *
     * @param SlugManager $slugManager Service used to resolve old paths.
     */
    public function __construct(private SlugManager $slugManager)
    {
    }

    /**
     * Event handler invoked on kernel exceptions.
     *
     * @param ExceptionEvent $event The kernel exception event.
     *
     * @return void
     */
    public function __invoke(ExceptionEvent $event)
    {
        if (!($event->getThrowable() instanceof NotFoundHttpException)) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!$path || empty($path)) {
            return;
        }

        $newPath = $this->slugManager->getNewPath($path);
        if (is_array($newPath) && isset($newPath['path']) && $newPath['path'] !== $path && str_starts_with($newPath['path'], '/')) {
            $absoluteUrl = $event->getRequest()->getSchemeAndHttpHost() . $newPath['path'];
            $event->setResponse(new RedirectResponse($absoluteUrl, Response::HTTP_MOVED_PERMANENTLY));
        }
    }
}
