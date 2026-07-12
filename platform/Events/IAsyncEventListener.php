<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Events;

/**
 * Marker interface to indicate that an event listener should be
 * executed asynchronously via the background queue instead of
 * synchronously blocking the current request.
 */
interface IAsyncEventListener {}
