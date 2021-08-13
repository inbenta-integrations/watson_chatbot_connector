<?php

namespace Inbenta\WatsonConnector\ExternalDigester;

use DOMDocument;
use \Exception;
use Inbenta\ChatbotConnector\ExternalDigester\Channels\DigesterInterface;

class WatsonDigester extends DigesterInterface
{
    protected $conf;
    protected $channel;
    protected $langManager;
    protected $session;

    public function __construct($langManager, $conf, $session)
    {
        $this->langManager = $langManager;
        $this->channel = 'PhoneCall';
        $this->conf = $conf;
        $this->session = $session;
    }

    /**
     *	Returns the name of the channel
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     *	Checks if a request belongs to the digester channel
     */
    public static function checkRequest($request)
    {
        $request = json_decode($request);

        $isMessaging = isset($request->activities[0]);
        if ($isMessaging && count($request->activities)) {
            return true;
        }
        return false;
    }

    /**
     * Can be used to log and alter raw input from Watson Action
     */
    protected function parseWatsonMessage($message)
    {
        return $message;
    }


    /**
     *	Formats a channel request into an Inbenta Chatbot API request
     */
    public function digestToApi($request)
    {
        if (is_null($request)) {
            return [];
        } else {
            $response = ['message' => $this->parseWatsonMessage($request->payload->input->text)];
        }
        return $response;
    }

    /**
     *	Formats an Inbenta Chatbot API response into a channel request
     */
    public function digestFromApi($request, $lastUserQuestion = '')
    {
        $messages = [];
        //Parse request messages
        if (isset($request->answers) && is_array($request->answers)) {
            $messages = $request->answers;
        } elseif ($this->checkApiMessageType($request) !== null) {
            $messages = array('answers' => $request);
        } elseif (isset($request->messages) && count($request->messages) > 0 && $this->hasTextMessage($messages[0])) {
            // If the first message contains text although it's an unknown message type, send the text to the user
            return $this->digestFromApiAnswer($messages[0], $lastUserQuestion);
        } else {
            throw new Exception("Unknown ChatbotAPI response: " . json_encode($request, true));
        }

        $output = [];
        foreach ($messages as $msg) {
            $msgType = $this->checkApiMessageType($msg);
            $digester = 'digestFromApi' . ucfirst($msgType);
            $digestedMessage = $this->$digester($msg, $lastUserQuestion);
            if ($digestedMessage == null) {
                continue;
            } else {
                $output = array_merge($output, $digestedMessage);
            }
        }
        return $output;
    }


    /**
     *	Classifies the API message into one of the defined $apiMessageTypes
     */
    protected function checkApiMessageType($message)
    {
        foreach ($this->apiMessageTypes as $type) {
            $checker = 'isApi' . ucfirst($type);

            if ($this->$checker($message)) {
                return $type;
            }
        }
        return null;
    }


    /********************** API MESSAGE TYPE CHECKERS **********************/

    protected function isApiAnswer($message)
    {
        return $message->type == 'answer';
    }

    protected function isApiPolarQuestion($message)
    {
        return $message->type == "polarQuestion";
    }

    protected function isApiMultipleChoiceQuestion($message)
    {
        return $message->type == "multipleChoiceQuestion";
    }

    protected function isApiExtendedContentsAnswer($message)
    {
        return $message->type == "extendedContentsAnswer";
    }

    protected function hasTextMessage($message)
    {
        return isset($message->text->body) && is_string($message->text->body);
    }





    /********************** CHATBOT API MESSAGE DIGESTERS **********************/

    protected function digestFromApiAnswer($message, $lastUserQuestion)
    {
        $output = [];
        $image = $this->handleMessageWithImages($message->message);
        if ($image) {
            $output = $image;
        } elseif (isset($message->message) && !empty(trim($message->message))) {
            $output[] = ["response_type" => "text", "text" => $this->cleanMessage($message->message)];
        }

        $exit = false;
        if (isset($message->attributes->DIRECT_CALL) && $message->attributes->DIRECT_CALL == "sys-goodbye") {
            $exit = true;
        }

        if (isset($message->attributes->SIDEBUBBLE_TEXT) && trim($message->attributes->SIDEBUBBLE_TEXT) !== "" && !$exit) {

            $sidebubble_text = $this->cleanMessage($message->attributes->SIDEBUBBLE_TEXT);
            if ($sidebubble_text) {
                $output[] = ["response_type" => "text", "text" => $sidebubble_text];
            }
            $image = $this->handleMessageWithImages($message->message);
            if ($image) {
                $output[] = ["response_type" => "text", "text" => $image];
            }
        }

        if (isset($message->actionField) && !empty($message->actionField) && $message->actionField->fieldType !== 'default' && !$exit) {
            $output[] = $this->handleMessageWithActionField($message, $lastUserQuestion);
        }
        $this->session->set('expecting_reply', false);
        return $output;
    }

    protected function digestFromApiMultipleChoiceQuestion($message, $lastUserQuestion, $isPolar = false)
    {
        $element = ["response_type" => "option", "title" => $this->cleanMessage($message->message)];

        $options = $message->options;

        foreach ($options as &$option) {
            if (isset($option->attributes->title)) {
                $value = $option->attributes->title;
            } else {
                $value = $option->value;
            }
            $element["options"][] = ["label" => $option->label, "value" => ["input" => ["text" => $value]]];

            if (isset($option->attributes->title) && !$isPolar) {
                $option->title = $option->attributes->title;
            } elseif ($isPolar) {
                $option->is_polar = true;
            }
        }
        $this->session->set('options', $options);
        $this->session->set('lastUserQuestion', $lastUserQuestion);
        $this->session->set('expecting_reply', true);
        return [$element];
    }

    protected function digestFromApiPolarQuestion($message, $lastUserQuestion)
    {

        return $this->digestFromApiMultipleChoiceQuestion($message, $lastUserQuestion, true);
    }


    protected function digestFromApiExtendedContentsAnswer($message, $lastUserQuestion)
    {
        $text = $this->cleanMessage($message->message);

        foreach ($message->subAnswers as $subAnswer) {
            $output[] = ["response_type" => "option", "title" => $this->cleanMessage($subAnswer->message)];
        }
        return $output;
    }


    /********************** MISC **********************/
    public function buildEscalationMessage()
    {
        return [];
    }

    public function buildEscalatedMessage()
    {
        return [];
    }

    public function buildInformationMessage()
    {
        return [];
    }

    public function buildContentRatingsMessage($ratingOptions, $rateCode)
    {
        return [];
    }
    public function buildUrlButtonMessage($message, $urlButton)
    {
        return [];
    }

    public function handleMessageWithImages($message)
    {
        return null;
    }


    /**
     * Clean the message from html and other characters
     * @param string $message
     */
    public function cleanMessage(string $message)
    {
        $message = str_replace("&nbsp;", " ", $message);
        $message = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', ' ', $message); //Unicode whitespace

        $message = str_replace("\n\t", "", $message);
        $message = str_replace("\n\n", "\n", $message);

        return $message;
    }


    /**
     * Validate if the message has action fields
     * @param object $message
     * @param array $output
     * @return array $output
     */
    protected function handleMessageWithActionField(object $message, $lastUserQuestion)
    {
        $output = "";
        if (isset($message->actionField) && !empty($message->actionField)) {
            if ($message->actionField->fieldType === 'list') {
                $output = $this->handleMessageWithListValues($message->actionField->listValues, $lastUserQuestion);
            }
        }
        return $output;
    }


    /**
     * Set the options for message with list values
     * @param object $listValues
     * @return array $output
     */
    protected function handleMessageWithListValues(object $listValues, $lastUserQuestion)
    {
        $element = ["response_type" => "option", "title" => ""];

        $options = $listValues->values;
        foreach ($options as $index => &$option) {
            $option->list_values = true;
            $option->label = $option->option;
            $element["options"][] = ["label" => $option->option, "value" => ["input" => ["text" => $option->label]]];
            if ($index == 5) break;
        }
        if (count($options) > 0) {
            $this->session->set('options', $options);
            $this->session->set('lastUserQuestion', $lastUserQuestion);
        }
        $this->session->set('expecting_reply', true);
        return $element;
    }
}
