<?php

declare(strict_types=1);

/*
 * Seconds each hand-written core file took in a mutation run (run 40150818183, 2026-10-25), so
 * `generator/bin/mutate --shard` can balance the shards by time: a file most tests cover, such as
 * the config, takes far longer than its size suggests. A file not listed counts as 30 seconds.
 * Refresh it when a shard runs much longer than the others.
 */
return [
    'src/Config/ProductiveConfig.php' => 1645,
    'src/Console/PingCommand.php' => 13,
    'src/Data/Attributes.php' => 376,
    'src/Data/InputData.php' => 14,
    'src/Data/Model.php' => 42,
    'src/Data/ModelRegistry.php' => 47,
    'src/Exceptions/ApiException.php' => 60,
    'src/Exceptions/RateLimitException.php' => 17,
    'src/Exceptions/ValidationException.php' => 5,
    'src/Http/CacheThrottle.php' => 786,
    'src/Http/Connector.php' => 455,
    'src/Http/Method.php' => 1,
    'src/Http/RateLimit.php' => 74,
    'src/Http/Request.php' => 59,
    'src/Http/Response.php' => 417,
    'src/Http/RetryPolicy.php' => 23,
    'src/JsonApi/Document.php' => 599,
    'src/JsonApi/DocumentBuilder.php' => 20,
    'src/JsonApi/ErrorObject.php' => 21,
    'src/JsonApi/Meta.php' => 3,
    'src/JsonApi/Relationship.php' => 205,
    'src/JsonApi/ResourceIdentifier.php' => 277,
    'src/JsonApi/ResourceIndex.php' => 31,
    'src/JsonApi/ResourceObject.php' => 160,
    'src/Pagination/FollowedLinks.php' => 0,
    'src/Pagination/ModelCollection.php' => 3,
    'src/ProductiveClient.php' => 15,
    'src/ProductiveServiceProvider.php' => 366,
    'src/Query/Condition.php' => 17,
    'src/Query/FilterGroup.php' => 10,
    'src/Query/Operator.php' => 4,
    'src/Query/Query.php' => 41,
    'src/Query/QuerySerializer.php' => 69,
    'src/Resources/PendingQuery.php' => 8,
    'src/Resources/Resource.php' => 225,
    'src/Testing/Concerns/FakesProductive.php' => 4,
    'src/Testing/Factories/Factory.php' => 49,
    'src/Testing/FakeConnector.php' => 67,
    'src/Testing/FakeResponse.php' => 83,
    'src/Testing/FakeWebhook.php' => 25,
    'src/Testing/ProductiveFake.php' => 47,
    'src/Testing/ResponseSequence.php' => 2,
    'src/Webhooks/VerifyProductiveSignature.php' => 13,
    'src/Webhooks/WebhookController.php' => 5,
    'src/Webhooks/WebhookDelivery.php' => 28,
    'src/Webhooks/WebhookEvent.php' => 16,
    'src/Webhooks/WebhookSignature.php' => 27,
];
