<?php
namespace Inbenta\SkypeConnector\ExternalAPI;

use \Exception;

class SkypeAPIClient extends SkypeBot
{
	protected $conversationId;
	protected $conversationName;
	protected $fromId;
	protected $fromName;
	protected $recipientId;
	protected $recipientName;
	protected $locale;
	protected $replyToId;
	protected $type = 'message';

	public function __construct($appId = null, $appPassword = null, $request = null)
	{
		parent::__construct($appId, $appPassword, $this->getServiceUrl($request));
		$this->setActivityPropertiesFromRequest($request);
	}

    /**
    *   Establishes the Skype activity properties from an incoming Skype request
    */
    protected function setActivityPropertiesFromRequest($request)
    {
        if (empty($request)) {
            return;
        }
        $request = json_decode($request);
        // Store conversation data
        $this->conversationId = !empty($request->conversation) && !empty($request->conversation->id) ? $request->conversation->id : '';
        $this->conversationName = !empty($request->conversation) && !empty($request->conversation->name) ? $request->conversation->name : '';
        // Store bot data
        $this->fromId = !empty($request->recipient) && !empty($request->recipient->id) ? $request->recipient->id : '';
        $this->fromName = !empty($request->recipientName) && !empty($request->recipient->name) ? $request->recipient->name : '';
        // Store user data
        $this->recipientId = !empty($request->from) && !empty($request->from->id) ? $request->from->id : '';
        $this->recipientName = !empty($request->from) && !empty($request->from->name) ? $request->from->name : '';
        // Store other conversation details
        $this->locale = !empty($request->locale) ? $request->locale : '';
        $this->replyToId = !empty($request->id) ? $request->id : '';
    }

    /**
     *   Establishes the Skype activity properties from an incoming Skype request
     */
    public function setActivityPropertiesFromHyperChat($data)
    {
        if (empty($data) || !is_array($data)) {
            return;
        }
        $data = $data;
        // Store conversation data
        $this->conversationId = !empty($data['conversation']) && !empty($data['conversation']['id']) ? $data['conversation']['id'] : '';
        $this->conversationName = !empty($data['conversation']) && !empty($data['conversation']['name']) ? $data['conversation'][['name']] : '';
        // Store bot data
        $this->recipientId = !empty($data['recipient']) && !empty($data['recipient']['id']) ? $data['recipient']['id'] : '';
        $this->recipientName = !empty($data['recipientName']) && !empty($data['recipient']['name']) ? $data['recipient']['name'] : '';
        // Store user data
        $this->fromId = !empty($data['from']) && !empty($data['from']['id']) ? $data['from']['id'] : '';
        $this->fromName = !empty($data['from']) && !empty($data['from']['name']) ? $data['from']['name'] : '';
        // Store other conversation details
        $this->locale = !empty($data['locale']) ? $data['locale'] : '';
        $this->replyToId = !empty($data['replyToId']) ? $data['replyToId'] : '';

        // Set serviceUrl (required by Skype to work)
        if (!empty($data['serviceUrl'])) {
            $this->setServiceUrl($data['serviceUrl']);
        }
    }

	/**
	*	Get the Skype service URL from an incoming Skype request
	*/
	protected function getServiceUrl($request)
    {
		$activity = json_decode($request);
		if (empty($activity->serviceUrl)) {
			return;
		}
    	return $activity->serviceUrl;
	}

	/**
	*	Returns the full name of the user (first + last name)
	*/
	public function getFullName()
    {
		return $this->recipientName;
	}

	/**
	*	Generates the external id used by HyperChat to identify one user as external.
	*	This external id will be used by HyperChat adapter to instance this client class from the external id
	*/
	public function getExternalId()
    {
        $userId         = $this->cleanString($this->recipientId);
        $conversationId = $this->cleanString($this->conversationId);
		return 'skype--' . $conversationId .'--'. $userId;
	}

    public function getMessageData()
    {
        return json_encode(array(
            'from' => array("id" => $this->fromId, "name" => $this->fromName),
            'conversation' => array("id" => $this->conversationId, "name" => $this->conversationName),
            'recipient' => array("id" => $this->recipientId, "name" => $this->recipientName),
            'replyToId' => $this->replyToId,
            'serviceUrl' => $this->serviceUrl
        ));
    }

	/**
	*	Retrieves the user id from the external ID generated by the getExternalId method	
	*/
	public static function getIdFromExternalId($externalId)
    {
		$SkypeInfo = explode('--', $externalId);
		if (array_shift($SkypeInfo) == 'skype') {
			return end($SkypeInfo);
        }
		return null;
	}

	public static function buildSessionIdFromRequest()
	{
        $request = json_decode(file_get_contents('php://input'), true);
		if (!empty($request['conversation']) && !empty($request['conversation']['id'])) {
			$clean_id = preg_replace('/[^a-zA-Z0-9\-]+/', '', $request['conversation']['id']);
			return $clean_id;
		}
		return null;
	}

	public function getEmail()
    {
        $userId   = $this->cleanString($this->recipientId);
        $userName = $this->cleanString($this->recipientName);
		return $userName ."_". $userId ."@skype.com";
	}

	/**
	*	Sends a flag to Skype to display a notification alert as the bot is 'writing'
	*	This method can be used to disable the notification if a 'false' parameter is received
	*/
    public function showBotTyping($show = true)
    {
		$activity = [
			'type' => "typing",
			'from' => array( "id" => $this->fromId, "name" => $this->fromName ),
			'conversation' => array( "id" => $this->conversationId, "name" => $this->conversationName ),
            'recipient' => array( "id" => $this->recipientId, "name" => $this->recipientName ),
            'replyToId' => $this->replyToId
        ];
        return $this->send($activity);
    }

    /**
    *	Sends a message to Skype. Needs a message formatted with the Skype notation
    */
	public function sendMessage($message = array())
    {
		$this->showBotTyping();
		$activity = [
			'type' => $this->type,
			'from' => array( "id" => $this->fromId, "name" => $this->fromName ),
			'conversation' => array( "id" => $this->conversationId, "name" => $this->conversationName ),
            'recipient' => array( "id" => $this->recipientId, "name" => $this->recipientName ),
            'replyToId' => $this->replyToId
        ];
        if (!empty($message['text'])) {
        	$activity['text'] = $message['text'];
        }
        if (!empty($message['suggestedActions'])) {
        	$activity['suggestedActions'] = $message['suggestedActions'];
        }
        if (!empty($message['attachments'])) {
            $activity['attachments'] = $message['attachments'];
        }
        if (!empty($message['attachmentLayout'])) {
            $activity['attachmentLayout'] = $message['attachmentLayout'];
        }
        return $this->send($activity);
	}

	/**
    *   Generates a text message from a string and sends it to Skype
    */
    public function sendTextMessage($text)
    {
        $this->sendMessage(["text" => $text]);
    }

    /**
    *   Generates a Skype attachment message from HyperChat message
    */
    public function sendAttachmentMessageFromHyperChat($message)
    {
        $this->sendMessage([
            'attachments' => [
                [
                    'contentType'   => $message['type'],            // Image type: "image/jpg"
                    'contentUrl'    => $message['contentBase64'],   // Image content in base64
                    'name'          => $message['name']             // Image file name: "my_image.jpg"
                ]
            ]
        ]);
    }

    public function cleanString($string)
    {
        return preg_replace('/[^a-zA-Z0-9\-]+/', '', $string);
    }
}
