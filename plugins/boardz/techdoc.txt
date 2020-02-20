TOA Extractor
##################################################################


get.php
--------

The get.php file provides a simple web service that makes an extraction of Moodle data and compile them within an output XML flow. 

The extraction query is secured and must be queried along with an SSL
encrypted ticket that provides query parameters and security information. The purpose is to avoid a "deny of service" attack
continuously triggering massive processing within Moodle. 

TOA extraction relies on an RSA ticket holding a json encoded hash array defined with following keys : 

- from : holds a UNIX timestamp denoting the "time from" the data are required

- query : holds a query identifier pointing the nature of information that is required.

- date : the date of the request for extraction as a UNIX timestamp. 

PHP implementation of the ticket is : 


$ticket = array('from' => $fromdate, 'query' => 'special_prf1', 'date' = time());

The get.php web service will require a TOA instance id to work with, so the correct URL is : 

$CFG->wwwroot.'/admin/report/etl/plugins/toa/get.php?id={$TOAID}&key={$TOAEXTRACTTICKET}

Note: encrypted $TOAEXTRACTTICKET content must be correctly urlencoded when appended to URL in GET urls. Pointing can also be done in POST requests.

The ticket must be encrypted using remote private RSA key. If the remote service is running in PHP, use the openssl_private_encrypt() function with the private key part, and provide the public key X509 cert to the TOA implementation in Moodle.

The answer is an XML document containing:

- extracted information
- an acknowledge encrypted(public) unique key 


Error situations : 

If an extraction is triggered back immediately when an other extraction is in progress, an error XML document will be sent.

acknowledging
---------------------------------------------------------------

This is done using a special sub_query : special_acknowledge, through the get.php service door.

Receives remote acknowledge of the ETL so tail processing can be triggered, such as cleaning logs etc.

The acknowledgment must provide within a TOA SSL ticket :

- the date of last accepted data
- the query name : special_acknowledge

The URL will hold : 

- the etl instance id
- the plugin name // foresees a generization of the get.php location
- the TOA ticket as key

http://<%%moodlehost%%>/admin/report/etl/plugins/toa/get.php?plugin=toa&id=1&key=XXXXXXXXXXXXXX

SSO
-----------------------------------------------------------

SSO communication provides a way for a user to roam from a logged Moodle account to a TOA account.

Moodle provides the user with an URL pointing to an SSO access door of TOA : 

http://<%%toahost%%>/sso/moodle.jsp?ssoticket=XXXXXXXXXXXXXXXXXX

The SSO ticket contains : 
- a "date" information
- a "login" field giving the user UID

Moodle provides TOA a way to get Identity Profile related information when importing a new user : 

http://<%%moodlehost%%>/admin/report/etl/plugins/toa/sso.php?id=1&method=des&key=XXXXXXXXXXXXXX

allows querying Moodle for a user profile.

The TOA SSO ticket contains : 
- a "date" information
- a "login" field to identify the required profile
- a "fields" coma separated list that designates moodle fields in the mdl_user table.

The response of the SSO profile delivery access contains an XML document holding an encoded response ticket : 

<?xml version="1.0"  encoding="<%encoding%>" ?>
<profile>
    <encrypteduser>XXXXXXXXXXXXXXXXXXXXXXXX</ecrypteduser>
</profile>

or an error message :

<?xml version="1.0"  encoding="<%%encoding%%>" ?>
<error>
    <errormsg><%%errormessage%%></errormsg>
</error>


