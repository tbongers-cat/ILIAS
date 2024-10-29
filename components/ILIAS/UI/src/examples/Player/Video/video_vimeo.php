<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Player\Video;

/**
 * ---
 * description: >
 *   Example for rendering a vimeo video player.
 *
 * expected output: >
 *   ILIAS shows a rendered video player with a vimeo video. On the left side you will see a Start/Stop
 *   symbol, followed by a time bar and on the right side a symbol for volume control and for the the full screen.
 *   A big start symbol is shown in the middle of the start screen. Clicking the Start/Stop symbol will start playing
 *   the video. Clicking the symbol again will stop the video.
 *
 *   In addition following functions have to be tested:
 *   - The video starts playing if clicking the start/stop symbol in the middle of the image. The video stops after another click.
 *   - The sound fades or raises if the volumes gets changed by using the volume control.
 *   - Clicking the full screen icon maximizes the video player to the size of the desktop size. Clicking ESC will diminish the video player.
 * ---
 */
function video_vimeo(): string
{
    global $DIC;
    $renderer = $DIC->ui()->renderer();
    $f = $DIC->ui()->factory();

    $video = $f->player()->video("https://vimeo.com/669475821?controls=0");

    return $renderer->render($video);
}
