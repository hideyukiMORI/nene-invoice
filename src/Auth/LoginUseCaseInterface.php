<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

interface LoginUseCaseInterface
{
    public function execute(LoginInput $input): LoginOutput;
}
