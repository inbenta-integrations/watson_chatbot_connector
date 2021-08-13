<?php

namespace Inbenta\WatsonConnector;

use \Exception;
use Inbenta\ChatbotConnector\ChatbotConnector;
use Inbenta\ChatbotConnector\ChatbotAPI\ChatbotAPIClient;
use Inbenta\ChatbotConnector\Utils\SessionManager;
use Inbenta\WatsonConnector\ExternalAPI\WatsonAPIClient;
use Inbenta\WatsonConnector\ExternalDigester\WatsonDigester;
use \Firebase\JWT\JWT;


class WatsonConnector extends ChatbotConnector
{
    private $messages;

    public function __construct($appPath)
    {
        // Initialize and configure specific components for Watson
        try {
            parent::__construct($appPath);

            // Initialize base components
            $request = file_get_contents('php://input');

            $conversationConf = array(
                'configuration' => $this->conf->get('conversation.default'),
                'userType'      => $this->conf->get('conversation.user_type'),
                'environment'   => $this->environment,
                'source'        => $this->conf->get('conversation.source')
            );

            //Validity check
            $this->validityCheck();

            $externalId = $this->getExternalIdFromRequest();
            $this->session = new SessionManager($externalId);

            $this->botClient = new ChatbotAPIClient(
                $this->conf->get('api.key'),
                $this->conf->get('api.secret'),
                $this->session,
                $conversationConf
            );

            $externalClient = new WatsonAPIClient(
                $this->conf->get('configuration.token'),
                $request
            ); // Instance Watson client

            // Instance Watson digester
            $externalDigester = new WatsonDigester(
                $this->lang,
                $this->conf->get('conversation.digester'),
                $this->session
            );
            $this->initComponents($externalClient, null, $externalDigester);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
            die();
        }
    }

    /**
     * Get the external id from request
     *
     * @return String 
     */
    protected function getExternalIdFromRequest()
    {
        // Try to get user_id from a Watson message request
        $externalId = WatsonAPIClient::buildExternalIdFromRequest();
        if (is_null($externalId)) {
            session_write_close();
            throw new Exception('Invalid request!');
        }
        return $externalId;
    }

    /**
     * Validate if the request is correct
     */
    protected function validityCheck()
    {
        $headers = getallheaders();
        if (!isset($headers['Authorization'])) {
            throw new Exception('Invalid request, no Watson JWT');
        }

        $jwt = $headers['Authorization'];
        try {
            $decoded = JWT::decode($jwt, $this->conf->get('configuration.token'), array('HS256'));
            if ($decoded) {
                return true;
            }
            echo json_encode(["error" => "Incorrect Secret."]);
            die;
        } catch (Exception $e) {
            echo json_encode(["error" => "Incorrect Secret."]);
            die;
        }
    }

    /**
     * 
     */
    protected function inbentaThreshold($request)
    {
        if ($this->session->get('expecting_reply', false)) {
            $this->session->set('expecting_reply', false);
            return true;
        }
        if (isset($request->payload->output->debug->turn_events) && count($request->payload->output->debug->turn_events) > 0) {
            foreach ($request->payload->output->debug->turn_events as $turn_event) {
                if (isset($turn_event->source->action)) {
                    if ($turn_event->source->action == "anything_else") {
                        return true;
                    } elseif ($turn_event->source->action == "welcome") {
                        return false;
                    }
                }
            }
        }
        if (isset($request->payload->output->debug->nodes_visited) && count($request->payload->output->debug->nodes_visited) > 0) {
            foreach ($request->payload->output->debug->nodes_visited as $node_visited) {
                if ($node_visited->title == "Anything else") {
                    return true;
                } elseif ($node_visited->title == "Welcome") {
                    return false;
                }
            }
        }
        if (isset($request->payload->output->intents) && count($request->payload->output->intents) > 0) {
            foreach ($request->payload->output->intents as $intent) {
                if ($intent->confidence >= $this->conf->get('configuration.threshold')) {
                    return false;
                }
            }
        }
        if (isset($request->payload->output->entities) && count($request->payload->output->entities) > 0) {
            foreach ($request->payload->output->entities as $entity) {
                if ($entity->confidence >= $this->conf->get('configuration.threshold')) {
                    return false;
                }
            }
        }
        return false;
    }

    /**
     * Save the structure of the input
     */
    public function handleInput($request)
    {
        $this->session->set('user_input', $request);
        return $request;
    }

    /**
     * 
     */
    public function handleOutput($request)
    {
        // Translate the request into a ChatbotAPI request
        $lastMessage = $this->session->get('user_input');

        if ($this->inbentaThreshold($request) && !empty(trim($lastMessage->payload->input->text))) {

            $externalRequest = $this->digester->digestToApi($lastMessage);
            if (!$externalRequest) return;
            // Check if it's needed to perform any action other than a standard user-bot interaction
            $nonBotResponse = $this->handleNonBotActions([$externalRequest]);
            if (!is_null($nonBotResponse)) {
                throw new Exception("Non-Bot Response is not null");
            }
            // Handle standard bot actions
            $this->handleBotActions([$externalRequest]);

            // Send all messages
            return $this->sendMessages($request);
        }
        return $request;
    }

    /**
     * Overwritten
     */
    public function handleRequest()
    {
        try {
            $request = json_decode(file_get_contents('php://input'));

            if (isset($request->payload->input)) {
                return $this->handleInput($request);
            } elseif (isset($request->payload->output)) {
                return $this->handleOutput($request);
            } else {
                throw new Exception("Invalid Request (neither input nor output)");
            }
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * Overwritten
     */
    protected function handleNonBotActions($digestedRequest)
    {
        // If user answered to an ask-to-escalate question, handle it
        if ($this->session->get('askingForEscalation', false)) {
            return $this->handleEscalation($digestedRequest);
        }
        return null;
    }

    /**
     * Print the message that Watson can process
     */
    public function sendMessages($request = null)
    {
        $request = ($request == null ? json_decode(file_get_contents('php://input')) : $request);
        $request->payload->output->generic = $this->messages;
        return $request;
    }

    /**
     * 
     */
    protected function sendMessagesToExternal($botResponse)
    {
        // Digest the bot response into the external service format
        $this->messages = $this->digester->digestFromApi($botResponse, $this->session->get('lastUserQuestion'));
    }

    /**
     * Overwritten
     */
    protected function handleEscalation($userAnswer = null)
    {
        $this->messages[] = ["response_type" => "text", "text" => $this->lang->translate('no_escalation')];
        return $this->sendMessages();
    }

    /**
     * Overwritten
     * Make the structure for Watson transfer
     */
    protected function escalateToAgent()
    {
        $this->trackContactEvent("CHAT_ATTENDED");
        $this->session->delete('escalationType');

        $this->session->delete('escalationV2');

        $this->messages = $this->digester->buildEscalatedMessage();
        $this->messages = $this->externalClient->escalate($this->conf->get('chat.chat.address'));
        return $this->sendMessages();
    }
}
