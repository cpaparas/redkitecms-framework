<?php
/**
 * This file is part of the RedKite CMS Application and it is distributed
 * under the GPL LICENSE Version 2.0. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) RedKite Labs <info@redkite-labs.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.redkite-labs.com
 *
 * @license    GPL LICENSE Version 2.0
 *
 */

namespace Controller\Queue;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use RedKiteCms\Rendering\Controller\Queue\SaveQueueController as BaseSaveQueueController;
use Symfony\Component\HttpFoundation\Response;

/**
 * This object implements the Silex controller to save the frontend operations queue to the backend
 *
 * @author  RedKite Labs <webmaster@redkite-labs.com>
 * @package Controller\Page
 */
class SaveQueueController extends BaseSaveQueueController
{
    /**
     * Saves the queue action
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \Silex\Application                        $app
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function saveAction(Request $request, Application $app)
    {
        $options = array(
            "request" => $request,
            "configuration_handler" => $app["red_kite_cms.configuration_handler"],
            'security' => $app["security"],
            "queue_manager" => $app["red_kite_cms.queue_manager"],
        );

        $response = parent::save($options);
        if ($app["red_kite_cms.queue_manager"]->hasQueue() && $response->getContent() == "Queue saved") {
            $lastRoute = $request->getSession()->get('last_uri');
            return $app->redirect($lastRoute);
        }

        return $response;
    }
}