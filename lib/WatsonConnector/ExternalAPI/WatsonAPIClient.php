<?php

namespace Inbenta\WatsonConnector\ExternalAPI;

class WatsonAPIClient
{
    /**
     * Create the external id
     */
    public static function buildExternalIdFromRequest()
    {
        $request = json_decode(file_get_contents('php://input'));

        if (!$request) {
            $request = (object)$_GET;
        }
        if (isset($request->payload->context->global->system->user_id)) {
            return str_replace("anonymous_", "", $request->payload->context->global->system->user_id);
        } else {
            return null;
        }
    }

    /**
     * Overwritten, not necessary with Watson
     */
    public function showBotTyping($show = true)
    {
        return true;
    }
}
