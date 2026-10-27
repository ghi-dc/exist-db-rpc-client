<?php

namespace ExistDbRpc;

/**
 * Client for interacting with an eXist-db instance via XML-RPC.
 *
 * See https://exist-db.org/exist/apps/doc/devguide_xmlrpc
 * and https://github.com/eXist-db/exist/blob/develop/exist-core/src/main/java/org/exist/xmlrpc/RpcAPI.java
 */
class Client
{
    /**
     * The URI to the database instance inclusive of the port.
     */
    protected string $uri;

    /**
     * The xml rpc client for the instance.
     */
    protected ?\fXmlRpc\Proxy $client;

    /**
     * The (optional) current collection context.
     */
    protected ?string $collection;

    protected $query;

    public function __construct(?array $options = null)
    {
        $defaults = [
            'protocol' => 'http',
            'user' => 'guest',
            'password' => 'guest',
            'host' => 'localhost',
            'port' => '8080',
            'path' => '/exist/xmlrpc/',
        ];

        if (!is_null($options)) {
            if (isset($options['collection'])) {
                $this->setCollection($options['collection']);
            }

            foreach ($defaults as $part => $value) {
                if (!isset($options[$part])) {
                    $options[$part] = $value;
                }
            }
        }
        else {
            $options = $defaults;
            $this->uri = 'http://guest:guest@localhost:8080/exist/xmlrpc/';
        }

        if (isset($options['uri'])) {
            $this->uri = $options['uri'];
        }
        else {
            $this->uri = $options['protocol'] . '://'
                       . $options['user'] . ':' . $options['password'] . '@'
                       . $options['host'] . ':' . $options['port'] . $options['path'];
        }

        $this->client = null;

        $httpClient = new \GuzzleHttp\Client();
        $client = new \fXmlRpc\Client(
            $this->uri,
            new \fXmlRpc\Transport\HttpAdapterTransport(
                new \GuzzleHttp\Psr7\HttpFactory(), // alternative is \Http\Message\MessageFactory\DiactorosMessageFactory
                new \GuzzleHttp\Psr7\HttpFactory(), // alternative is \Http\Message\StreamFactory\DiactorosStreamFactory
                new \Http\Adapter\Guzzle7\Client($httpClient)
            )
        );

        $this->client = new \fXmlRpc\Proxy($client);
    }

    /**
     * Get the URI to the database instance.
     *
     * @return string the URI
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Set the current collection context.
     *
     * @param string|null $collection the collection path
     */

    public function setCollection(?string $collection): void
    {
        if (!empty($collection)) {
            $collection = rtrim($collection, '/');
        }

        $this->collection = $collection;
    }

    /**
     * Get the current collection context.
     *
     * @return string|null the collection path
     */
    public function getCollection(): ?string
    {
        return $this->collection;
    }

    protected function assureSerializeParameters(array $parameters): array
    {
        // [] gets serialized as array, so we we add one default parameters
        if (empty($parameters)) {
            return ['indent' => 'no'];
        }

        return $parameters;
    }

    /**
     * Prepend the collection path to the document name if necessary.
     *
     * @param string $docName the document name
     *
     * @return string the full document path
     */
    protected function buildDocumentPath(string $docName): string
    {
        if (is_null($this->collection)) {
            return $docName;
        }

        if (!empty($docName) && '/' === $docName[0]) {
            // $docName is absolute
            return $docName;
        }

        // $docName is relative, so prepend collection
        return $this->collection . '/' . $docName;
    }

    /**
     * Prepend the collection path to the collection name if necessary.
     *
     * @param string|null $collection the collection name
     *
     * @return string|null the full collection path
     */
    protected function buildCollectionPath(?string $collection): ?string
    {
        return !is_null($collection)
            ? $collection : $this->collection;
    }

    /**
     * Return the database version.
     *
     * @return string database version
     */
    public function getVersion(): string
    {
        // @phpstan-ignore method.notFound
        return $this->client->getVersion();
    }

    /**
     * Retrieve document by name.
     *
     * @param string $name       the document's name
     * @param array  $parameters map of parameters
     *
     * @return mixed The document value
     */
    public function getDocument(string $name, array $parameters = []): mixed
    {
        $parameters = $this->assureSerializeParameters($parameters);

        try {
            // @phpstan-ignore method.notFound
            $val = $this->client->getDocument($this->buildDocumentPath($name), $parameters);

            return $val->getDecoded();
        }
        catch (\fXmlRpc\Exception\FaultException $e) {
            return false;
        }
    }

    /**
     * Retrieve document by name.
     *
     * @param string     $docName    the document's name
     * @param array|null $parameters map of parameters
     *
     * @return string|null The document value
     */
    public function getDocumentAsString(string $docName, ?array $parameters = []): ?string
    {
        $parameters = $this->assureSerializeParameters($parameters);

        // @phpstan-ignore method.notFound
        return $this->client->getDocumentAsString($this->buildDocumentPath($docName), $parameters);
    }

    /**
     * Retrieve binary resource by name.
     *
     * @param string $name the resource's name
     *
     * @return mixed The resource content
     */
    public function getBinaryResource(string $name): mixed
    {
        try {
            // @phpstan-ignore method.notFound
            $val = $this->client->getBinaryResource($this->buildDocumentPath($name));

            return $val->getDecoded();
        }
        catch (\fXmlRpc\Exception\FaultException $e) {
            return false;
        }
    }

    /**
     * Does the document identified by name exist in the
     * repository?
     *
     * @param string $name The name of the document
     *
     * @return bool true if the document exists, false otherwise
     */
    public function hasDocument(string $name): bool
    {
        // @phpstan-ignore method.notFound
        return $this->client->hasDocument($this->buildDocumentPath($name));
    }

    /**
     * Get a list of all documents contained in $collection or (if null) the database.
     *
     * @param string|null $collection the collection name
     *
     * @return array a list of documents contained in the specified collection or the database if null
     */
    public function getDocumentListing(?string $collection = null): array
    {
        $collectionPath = $this->buildCollectionPath($collection);

        return is_null($collectionPath)
            ? $this->client->getDocumentListing()                   // @phpstan-ignore method.notFound
            : $this->client->getDocumentListing($collectionPath)    // @phpstan-ignore method.notFound
        ;
    }

    /**
     * Does the collection identified by name exist in the
     * repository?
     *
     * @param string|null $name of the collection
     *
     * @return bool true if the collection exists, false otherwise
     */
    public function hasCollection(?string $name = null): bool
    {
        // @phpstan-ignore method.notFound
        return $this->client->hasCollection($this->buildCollectionPath($name));
    }

    /**
     * Get a list of (sub)collections in $collection.
     *
     * @param string|null $name of the collection
     *
     * @return mixed a list of (sub)collections contained in the specified collection
     */
    public function getCollectionListing(?string $name = null): mixed
    {
        // @phpstan-ignore method.notFound
        return $this->client->getCollectionListing($this->buildCollectionPath($name));
    }

    /**
     * Determines whether a Collection exists in the database and whether the
     * user may open the collection.
     *
     * @param string|null $name of the collection
     *
     * @return bool returns true if the collection exists and can be opened, false otherwise
     */
    public function existsAndCanOpenCollection(?string $name = null): bool
    {
        // @phpstan-ignore method.notFound
        return $this->client->existsAndCanOpenCollection($this->buildCollectionPath($name));
    }

    /**
     * Describe a collection: returns a struct with the following fields:.
     *
     * @param string|null $name of the collection
     *
     * @return mixed A struct describing the collection.
     *
     *	name			The name of the collection
     *	owner			The name of the user owning the collection.
     *	group			The group owning the collection.
     *	permissions	    The permissions that apply to this collection (int value)
     *	created			The creation date of this collection (long value)
     *	collections		An array containing the names of all subcollections.
     *	documents		An array containing a struct for each document in the collection.
     */
    public function getCollectionDesc(?string $name = null): mixed
    {
        // @phpstan-ignore method.notFound
        return $this->client->getCollectionDesc($this->buildCollectionPath($name));
    }

    /**
     * Describe a collection: similar to getCollectionDesc but without documents.
     *
     * @param string|null $name of the collection
     *
     * @return mixed a struct describing the collection
     */
    public function describeCollection(?string $name = null): mixed
    {
        // @phpstan-ignore method.notFound
        return $this->client->describeCollection($this->buildCollectionPath($name));
    }

    /**
     * Describe a resource.
     *
     * @param string $resource the path to the resource
     *
     * @return mixed returns a struct describing a specified resource
     */
    public function describeResource(string $resource): mixed
    {
        // @phpstan-ignore method.notFound
        return $this->client->describeResource($this->buildDocumentPath($resource));
    }

    /**
     * Returns the number of resources in the collection identified by
     * collection.
     *
     * @param string|null $collection the name of the collection
     *
     * @return int the number of resources in the collection
     */
    public function getResourceCount(?string $collection = null): int
    {
        // @phpstan-ignore method.notFound
        return $this->client->getResourceCount($this->buildCollectionPath($collection));
    }

    /**
     * Parse an XML document and store it into the database. The document will
     * later be identified by <code>docName</code>. Some xmlrpc clients seem to
     * have problems with character encodings when sending xml content. To avoid
     * this, parse() accepts the xml document content as byte[]. If
     * <code>overwrite</code> is &gt;0, an existing document with the same name
     * will be replaced by the new document.
     *
     * @param string $xml       the document data
     * @param string $docName   the path where the document will be stored
     * @param bool   $overwrite whether to overwrite an existing document
     *
     * @return bool true, if the document is valid XML
     */
    public function parse(string $xml, string $docName, bool $overwrite = false): bool
    {
        // @phpstan-ignore method.notFound
        return $this->client->parse($xml, $this->buildDocumentPath($docName), $overwrite ? 1 : 0);
    }

    /**
     * An Alias for parse().
     *
     * @param string $xml       the document data
     * @param string $docName   the path where the document will be stored
     * @param bool   $overwrite whether to overwrite an existing document
     *
     * @return bool true, if the document is valid XML
     */
    public function storeDocument(string $xml, string $docName, bool $overwrite = false): bool
    {
        return $this->parse($xml, $docName, $overwrite);
    }

    /**
     * Store data as a binary resource.
     *
     * @param mixed  $data     the data to be stored
     * @param string $docName  the path to the new document
     * @param string $mimeType the mimeType to check for
     * @param bool   $replace  if true, an old document with the same path will be replaced
     *
     * @return bool true, if the binary data was successfully stored
     */
    public function storeBinary($data, string $docName, string $mimeType, bool $replace = false): bool
    {
        // @phpstan-ignore method.notFound
        return $this->client->storeBinary(
            \fXmlRpc\Value\Base64::serialize($data),
            $this->buildDocumentPath($docName),
            $mimeType,
            $replace ? 1 : 0
        );
    }

    /**
     * Remove a document from the database.
     *
     * @param string $docName path to the document to be removed
     *
     * @return bool true, if the document could be removed
     */
    public function remove(string $docName): bool
    {
        // @phpstan-ignore method.notFound
        return $this->client->remove($this->buildDocumentPath($docName));
    }

    /**
     * Remove an entire collection from the database.
     *
     * @param string|null $name path to the collection to be removed
     *
     * @return bool true, if the collection could be removed
     */
    public function removeCollection(?string $name = null): bool
    {
        // @phpstan-ignore method.notFound
        return $this->client->removeCollection($this->buildCollectionPath($name));
    }

    /**
     * Create a new collection on the database.
     *
     * @param string|null $name the path to the new collection
     *
     * @return bool true, if the collection could be created
     */
    public function createCollection(?string $name = null): bool
    {
        // @phpstan-ignore method.notFound
        return $this->client->createCollection($this->buildCollectionPath($name));
    }

    /**
     * Get the creation date of a collection.
     *
     * @param string|null $collectionName the name of the collection
     *
     * @return string the creation date of the collection
     */
    public function getCreationDate(?string $collectionName = null): string
    {
        // @phpstan-ignore method.notFound
        return $this->client->getCreationDate($this->buildCollectionPath($collectionName));
    }

    /**
     * Get the timestamps of a document.
     *
     * @param string|null $documentName the name of the document
     *
     * @return array the timestamps of the document
     */
    public function getTimestamps(?string $documentName = null): array
    {
        // @phpstan-ignore method.notFound
        return $this->client->getTimestamps($this->buildDocumentPath($documentName));
    }

    /**
     * Prepare the execution of an XPath/XQuery.
     *
     * @param string      $xql        the XPath/XQuery to be executed
     * @param string|null $collection the collection context for the query
     *
     * @return Query the prepared query object
     */
    public function prepareQuery(string $xql, ?string $collection = null): Query
    {
        $query = new Query($xql, $this->client, $this->buildCollectionPath($collection));

        return $query;
    }

    /**
     * Determine the permissions of a resource.
     *
     * @param string $resource the resource for which to determine permissions
     *
     * @return array the permissions of the resource
     */
    public function getPermissions(string $resource): array
    {
        // @phpstan-ignore method.notFound
        return $this->client->getPermissions($this->buildDocumentPath($resource));
    }

    /**
     * Get the members of a group.
     *
     * @param string $groupName the name of the group
     *
     * @return array the members of the group
     */
    public function getGroupMembers(string $groupName): array
    {
        // @phpstan-ignore method.notFound
        return $this->client->getGroupMembers($groupName);
    }

    /**
     * Get all the groups.
     *
     * @return array the list of all groups
     */
    public function getGroups(): array
    {
        // @phpstan-ignore method.notFound
        return $this->client->getGroups();
    }

    public function getGroup(string $name): array
    {
        // @phpstan-ignore method.notFound
        return $this->client->getGroup($name);
    }

    /**
     * Get all the accounts.
     *
     * @return array the list of all accounts
     */
    public function getAccounts()
    {
        // @phpstan-ignore method.notFound
        return $this->client->getAccounts();
    }

    /**
     * Get the details of an account.
     *
     * @param string $name the name of the account
     *
     * @return array the details of the account
     */
    public function getAccount(string $name): array
    {
        // @phpstan-ignore method.notFound
        return $this->client->getAccount($name);
    }

    /**
     * Reindex a collection.
     *
     * @param string|null $name the name of the collection to reindex
     *
     * @return bool the result of the reindex operation
     */
    public function reindexCollection(?string $name = null): bool
    {
        // @phpstan-ignore method.notFound
        return $this->client->reindexCollection($this->buildCollectionPath($name));
    }

    /*
     * TODO: as per https://github.com/eXist-db/exist/blob/develop/exist-core/src/main/java/org/exist/xmlrpc/RpcAPI.java
     *
     *  shutdown()
     *  shutdown(long delay)
     *
     *  sync()
     *
     *  enterServiceMode()
     *  exitServiceMode()
     *
     *  boolean addGroup(String name, Map<String, String> metadata)
     *  boolean updateGroup(final String name, final List<String> managers, final Map<String, String> metadata)
     *  void removeGroup(String name)
     *
     *  boolean setUserPrimaryGroup(final String username, final String groupName)
     *  void addAccountToGroup(final String accountName, final String groupName)
     *  void addGroupManager(final String manager, final String groupName)
     *  void removeGroupManager(final String groupName, final String manager)
     *  void removeGroupMember(final String group, final String member)
     *
     *  addAccount(String name, String passwd, String digestPassword, List<String> groups,
     *              Boolean isEnabled, Integer umask, Map<String, String> metadata)
     *  updateAccount(String name, String passwd, String digestPassword, List<String> groups)
     *  boolean removeAccount(String name)
     *
     *  boolean setPermissions(String resource, String permissions)
     *  boolean chgrp(final String resource, final String ownerGroup)
     *  boolean chown(final String resource, final String owner)
     *
     *  boolean lockResource(String path, String userName)
     *  boolean unlockResource(String path)
     *  String hasUserLock(String path)
     *
     *  Map<String, Object> getDocumentData(String name, Map<String, Object> parameters)
     *  Map<String, Object> getNextChunk(String handle, int offset)
     *  Map<String, Object> getNextExtendedChunk(String handle, String offset)
     *
     *  Map<String, Object> compile(byte[] xquery, Map<String, Object> parameters)
     *
     *  Map<String, Object> querySummary(String xquery)
     *  Map<String, Object> querySummary(int resultId)
     *
     *
     *  int xupdate(String collectionName, byte[] xupdate)
     *  int xupdateResource(String resource, byte[] xupdate)
     *
     *  boolean setLastModified(final String documentPath, final long lastModified)
     *  List<String> getDocType(String documentName)
     *  boolean setDocType(String documentName, String doctypename, String publicid, String systemid)
     *
     *  boolean copyCollection(String name, String namedest)
     *  boolean moveCollection(String collectionPath, String destinationPath, String newName)
     *
     *  boolean copyResource(String docPath, String destinationPath, String newName)
     *  boolean moveResource(String docPath, String destinationPath, String newName)
     *
     *  List<List> getIndexedElements(String collectionName, boolean inclusive)
     *  boolean reindexCollection(String name)
     *  boolean reindexDocument(String docUri)
     *
     *  String printDiagnostics(String query, Map<String, Object> parameters)
     *
     *  String createResourceId(String collection)
     *
     *  boolean configureCollection(String collection, String configuration)
     *
     *  boolean backup(String userbackup, String password, String destcollection, String collection)
     *  boolean dataBackup(String dest)
     *
     *  void runCommand(XmldbURI collectionURI, List<String> params)
     *
     *  long getSubCollectionCreationTime(String parentPath, String name)
     *  Map<String, Object> getSubCollectionPermissions(String parentPath, String name)
     *  Map<String, Object> getSubResourcePermissions(String parentPath, String name)
     *
     *  boolean setTriggersEnabled(String path, String value)
     *
     *  For handling huge files, maybe add the following
     *      String upload(String file, byte[] chunk, int length)
     *      boolean parseLocal(String localFile, String docName, boolean replace, String mimeType)
     *
     *  The following don't seem to work properly in 4.0.0?!
     *  	Map<String, List> listDocumentPermissions(String name)
     *  	Map<XmldbURI, List> listCollectionPermissions(String name)
     *
     *  	List<String> getDocType(String documentName)
     *
     */
}
