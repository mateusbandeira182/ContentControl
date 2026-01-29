<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MkGrow\ContentControl\ContentControl;

// Debug script to understand PHPWord Header/Footer structure

$cc = new ContentControl();
$section = $cc->addSection();

$header = $section->addHeader('default');
$headerText = $header->addText('Header content');

$bodyText = $section->addText('Body content');

echo "=== Header Object ===" . PHP_EOL;
echo "Class: " . get_class($header) . PHP_EOL;
$headerReflection = new ReflectionClass($header);
echo "Properties:" . PHP_EOL;
foreach ($headerReflection->getProperties() as $prop) {
    echo "  - " . $prop->getName() . " (";
    if ($prop->isPrivate()) echo "private";
    if ($prop->isProtected()) echo "protected";
    if ($prop->isPublic()) echo "public";
    echo ")" . PHP_EOL;
}

echo PHP_EOL . "=== Header Text Object ===" . PHP_EOL;
echo "Class: " . get_class($headerText) . PHP_EOL;
$textReflection = new ReflectionClass($headerText);
echo "Properties:" . PHP_EOL;
foreach ($textReflection->getProperties() as $prop) {
    echo "  - " . $prop->getName() . " (";
    if ($prop->isPrivate()) echo "private";
    if ($prop->isProtected()) echo "protected";
    if ($prop->isPublic()) echo "public";
    
    $prop->setAccessible(true);
    $value = $prop->getValue($headerText);
    echo ") = ";
    if (is_object($value)) {
        echo get_class($value);
    } elseif (is_null($value)) {
        echo "null";
    } else {
        echo var_export($value, true);
    }
    echo PHP_EOL;
}

echo PHP_EOL . "=== Body Text Object ===" . PHP_EOL;
echo "Class: " . get_class($bodyText) . PHP_EOL;
$bodyTextReflection = new ReflectionClass($bodyText);
echo "Properties:" . PHP_EOL;
foreach ($bodyTextReflection->getProperties() as $prop) {
    echo "  - " . $prop->getName() . " (";
    if ($prop->isPrivate()) echo "private";
    if ($prop->isProtected()) echo "protected";
    if ($prop->isPublic()) echo "public";
    
    $prop->setAccessible(true);
    $value = $prop->getValue($bodyText);
    echo ") = ";
    if (is_object($value)) {
        echo get_class($value);
    } elseif (is_null($value)) {
        echo "null";
    } else {
        echo var_export($value, true);
    }
    echo PHP_EOL;
}
