<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

/** Published so Events can welcome a visitor without reading People tables. Runs inside the caller's transaction. */
interface VisitorRegistrar
{
    /**
     * Finds the person who already holds the visitor's mobile number or email, or registers a new VISITOR.
     * A person that is found is never changed by what a visitor typed on a public form.
     */
    public function registerOrFind(VisitorDetails $details): VisitorPerson;
}
