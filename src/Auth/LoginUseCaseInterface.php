<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

interface LoginUseCaseInterface
{
    /** @throws InvalidCredentialsException */
    public function execute(LoginInput $input): LoginOutput;
}
