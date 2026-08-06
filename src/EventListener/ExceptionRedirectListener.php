<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Wisymfony\SlugHistoryBundle\Service\SlugManager;

#[AsEventListener(event : KernelEvents::EXCEPTION, priority: 10)]
class ExceptionRedirectListener
{
    public function __construct(private SlugManager $slugManager){
    }

    public function __invoke(ExceptionEvent $event) {
        if (!($event->getThrowable() instanceof NotFoundHttpException)) return;

        $path = $event->getRequest()->getPathInfo();
        if (!$path || empty($path)) return;
        
        $newPath = $this->slugManager->getNewPath($path);
        if ($newPath != $path) {
            $event->setResponse(new RedirectResponse($newPath, Response::HTTP_MOVED_PERMANENTLY));
        }
    }
}
