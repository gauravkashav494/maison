<?php

namespace App\Http\Requests\Storefront;

/** The code a shopper received on WhatsApp. Spaces typed between digits are ignored. */
class OtpCodeRequest extends StorefrontRequest
{
    protected array $lowercase = [];

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/\s+/', '', $this->input('code'))]);
        }
    }

    public function rules(): array
    {
        $length = (int) config('services.otp.length', 6);

        return ['code' => ['required', 'string', "regex:/^\\d{{$length}}$/"]];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Please enter the code we sent you on WhatsApp.',
            'code.regex' => 'The code is '.config('services.otp.length', 6).' digits.',
        ] + parent::messages();
    }
}
