<?php

namespace Rector\Tests\TypeDeclaration\Rector\JsonUnescapedUnicodeRector\Fixture;

$content = [
  "nom" => "Alice",
  "age" => 30,
  "ville" => "Paris",
  "hobbies" => ["lecture", "voyage", "cinema"]
];
$json = json_encode($content);
json_encode($content);
json_decode($json);

?>
-----
<?php

namespace Rector\Tests\TypeDeclaration\Rector\JsonUnescapedUnicodeRector\Fixture;

$content = [
  "nom" => "Alice",
  "age" => 30,
  "ville" => "Paris",
  "hobbies" => ["lecture", "voyage", "cinema"]
];
$json = json_encode($content);
json_encode($content, JSON_UNESCAPED_UNICODE);
json_decode($json, null, 512, JSON_UNESCAPED_UNICODE);

?>
