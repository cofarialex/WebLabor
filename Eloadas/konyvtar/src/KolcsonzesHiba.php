<?php
declare(strict_types=1);

// Üzleti szabály megsértése (pl. a példány már ki van adva). Az üzenete
// a könyvtárosnak szól, ezért kiírható az oldalra.
final class KolcsonzesHiba extends RuntimeException
{
}
