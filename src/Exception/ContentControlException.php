<?php

namespace MkGrow\ContentControl\Exception;

/**
 * Base exception class for all ContentControl-related errors
 * 
 * All custom exceptions in this library extend this class,
 * allowing consumers to catch all library errors with a single handler.
 * 
 * @package MkGrow\ContentControl\Exception
 */
class ContentControlException extends \RuntimeException
{
}
