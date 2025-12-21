<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Quiz.php';

$quiz = new Quiz();
echo "Fillable Attributes:\n";
$reflection = new ReflectionClass($quiz);
$property = $reflection->getProperty('fillable');
$property->setAccessible(true);
$fillable = $property->getValue($quiz);
print_r($fillable);

$data = ['teacher_id' => 123, 'title' => 'Test'];
$filtered = array_intersect_key($data, array_flip($fillable));
echo "Filtered Data for ['teacher_id' => 123, 'title' => 'Test']:\n";
print_r($filtered);
