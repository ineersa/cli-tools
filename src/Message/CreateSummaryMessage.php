<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('summary')]
final class CreateSummaryMessage
{

     public function __construct(
         public readonly string $name,
     ) {
     }
}
