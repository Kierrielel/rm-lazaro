<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;

class LiteralSearch
{
    /**
     * @param  list<string>  $columns  Trusted application column names.
     */
    public static function apply(Builder $query, array $columns, string $search): void
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $query->where(function (Builder $query) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $wrapped = $query->getQuery()->getGrammar()->wrap($column);
                $query->orWhereRaw("{$wrapped} LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }
}
