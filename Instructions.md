# WATSON ASSISTANT - FEDERATED SEARCH INTEGRATION
### TABLE OF CONTENTS
* [Introduction](#introduction)
* [Features](#features)
* [Building the Watson Assistant Connector](#building-the-watson-connector)
    * [Required Configuration](#required-configuration)
    * [Optional Configuration](#optional-configuration)
    * [Deployment](#deployment)
* [Watson Configuration](#watson-configuration)
    * [Account](#account)
    * [Token](#token)
* [Prepare your Inbenta instances](#prepare-your-inbenta-instances)
    * [Text Content](#text-content)


## **Introduction**
You can extend your [IBM Watson Assistant](https://developer.amazon.com/watson/console/ask?)'s capabilities with using this connector to integrate with the Inbenta Chatbot.

## **Features**
The following features of the Inbenta Chatbot are supported in the Watson integration:
* HTML tags
* Mulitiple Choice Questions
* Simple Answers

## **Building the Watson Connector**

### **Required Configuration**

In your UI directory, go to **conf**. Here, you have a readme file with some structure and usage explanations.

Fill the **key** and **secret** values inside the **conf/custom/api.php** file with your Inbenta Federated Search API credentials ([Here](https://help.inbenta.com/en/general/administration/managing-credentials-for-developers/finding-your-instance-s-api-credentials/) is the documentation on how to find the key and secret from Inbenta’s backstage. Use the same credentials as backstage to access the article).

### **Optional Configuration**
In the **conf/custom/configuration.php** file, you can change the `threshold` value from `0.5` to any other value in the range of `0.0` to     `1.0`. This value determines what responses from Watson will be replaced by the call to the Inbenta Chatbot instance. It is compared to the confidence value that Watson returns with all responses; if the confidence of a response is lower that the threshold value, the Search is called.
```php
return [
    'token' => '*****************',
    'threshold' => 0.5
];
```


### **Deployment**
The Watson Assistant connector must be served by a public web server in order to allow the Watson Assistant to send the events to it. The environment where the template has been developed and tested has the following specifications

*   Apache 2.4
*   PHP 7.3
*   PHP Curl extension
*   Non-CPU-bound
*   The latest version of [**Composer**](https://getcomposer.org/) (Dependency Manager for PHP) to install all dependencies that Inbenta requires for the integration.
*   If the client has a **distributed infrastructure**, this means that multiple servers can manage the user session, they must adapt their SessionHandler so that the entire session is shared among all its servers.


# **Watson Configuration**

## **Account**

Log in to your Watson account and go to your [Watson Actions Console](https://login.ibm.com/) or create a new one following [these steps](https://cloud.ibm.com/docs/assistant?topic=assistant-getting-started).

Go to the **Assistants** menu and click on **Create assistant**. When prompted, enter a name and a description that will help you recognize it, and click on **Create assistant** at the bottom of the form.

That you've made your Assistant, we will change its settings. Click on  **⋮** (on the top-right, next to **Preview**) and then **Assistant Settings**. Now, go to **Webhooks** and do the following for both **Pre-message webhook** and **Post-message webhook**:
* Enable the webhook using the first toggle
* Enter the URL of webhook where the connector is hosted.
* Enter a personal **token** (the connector will always expect the same token, so enter the same value for pre-message and post-message)


## **Token**

The **token** you defined above for the webhook calls is a password-like value, which can be used to ensure that the incoming calls from Watson are coming from the Assistant you created and not some other one.
Once you have it on your clipboard, paste it into the **conf/custom/configuration.php** file:

```php
return [
    'token' => 'replace_this_with_your_token',
    'threshold' => 0.5
];
```

# **Prepare your Inbenta instances**

## **Text Content**
The Watson Assistant accepts some but not all HTML tags, multimedia and URLs included directly in the text response, so it is important to keep that in mind when creating your content.