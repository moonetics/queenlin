<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\ManualFullDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EventRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'event_date' => $this->normalizeEventDate($this->input('event_date')),
            'prizepool' => Event::normalizePrizepool($this->input('prizepool')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.Event::POSTER_MAX_KILOBYTES],
            'theme' => ['required', 'string', 'max:255'],
            'map_name' => ['required', 'string', 'max:255'],
            'prizepool' => ['nullable', 'string', 'max:255'],
            'event_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in([Event::STATUS_DRAFT, Event::STATUS_PUBLISHED])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_time', 'end_time'])) {
                    return;
                }

                $start = $this->minutes($this->input('start_time'));
                $end = $this->minutes($this->input('end_time'));
                $normalizedEnd = $end === 0 ? Event::OPERATIONAL_END_MINUTES : $end;
                $event = $this->route('event');
                $exceptEventId = $event instanceof Event ? $event->id : null;
                $isEditingSameDate = $event instanceof Event
                    && $event->event_date?->toDateString() === (string) $this->input('event_date');

                if ($start < 900 || $start > 1439) {
                    $validator->errors()->add('start_time', 'Jam mulai harus berada di antara 15:00 dan 23:59 WIB.');
                }

                if ($end !== 0 && $end <= $start) {
                    $validator->errors()->add('end_time', 'Jam selesai harus setelah jam mulai, atau gunakan 00:00 untuk tengah malam.');
                }

                if ($normalizedEnd - $start < Event::MIN_BOOKING_MINUTES) {
                    $validator->errors()->add('end_time', 'Durasi event minimal 5 menit.');
                }

                if ($validator->errors()->hasAny(['event_date', 'start_time', 'end_time'])) {
                    return;
                }

                if (ManualFullDate::isClosedDate($this->input('event_date')) && ! $isEditingSameDate) {
                    $validator->errors()->add('event_date', 'Tanggal ini ditandai penuh atau libur dan tidak bisa dipilih lagi.');

                    return;
                }

                if (Event::isDateClosed($this->input('event_date'), $exceptEventId, ! $isEditingSameDate)) {
                    $validator->errors()->add('event_date', 'Tanggal ini sudah penuh dan tidak bisa dipilih lagi.');

                    return;
                }

                $hasOverlap = Event::query()
                    ->forDate($this->input('event_date'))
                    ->when($exceptEventId, fn ($query) => $query->whereKeyNot($exceptEventId))
                    ->get()
                    ->contains(fn (Event $event) => Event::timeRangeOverlaps(
                        $this->input('start_time'),
                        $this->input('end_time'),
                        $event->start_time,
                        $event->end_time,
                    ));

                if ($hasOverlap) {
                    $validator->errors()->add('start_time', 'Jam ini sudah dipilih untuk event lain pada tanggal tersebut.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul event',
            'poster' => 'poster event',
            'theme' => 'tema event',
            'map_name' => 'nama map',
            'prizepool' => 'prizepool',
            'event_date' => 'tanggal event',
            'start_time' => 'jam mulai',
            'end_time' => 'jam selesai',
            'description' => 'deskripsi',
            'status' => 'status publikasi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_date.required' => 'Tanggal event wajib dipilih.',
            'event_date.date_format' => 'Tanggal event tidak valid. Pilih tanggal dari daftar yang tersedia.',
            'poster.uploaded' => 'Poster event gagal diupload. Server saat ini menerima file maksimal '.Event::POSTER_MAX_MEGABYTES.' MB.',
            'poster.image' => 'Poster event harus berupa gambar.',
            'poster.mimes' => 'Poster event harus berformat JPG, PNG, atau WebP.',
            'poster.max' => 'Poster event maksimal '.Event::POSTER_MAX_MEGABYTES.' MB.',
        ];
    }

    private function normalizeEventDate(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value));

        if ($value === '') {
            return $value;
        }

        $normalizedValue = $this->normalizeMonthName($value);
        $candidateValues = [$normalizedValue];

        if (preg_match('/^(\d{1,2}\s+[A-Za-z]+\s+\d{4})\b/', $normalizedValue, $matches) === 1) {
            $candidateValues[] = $matches[1];
        }

        $formats = ['Y-m-d', 'd M Y', 'd F Y'];

        foreach (array_unique($candidateValues) as $candidateValue) {
            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat('!'.$format, $candidateValue);

                    if ($date !== false && $date->format($format) === $candidateValue) {
                        return $date->toDateString();
                    }
                } catch (\Throwable) {
                    //
                }
            }
        }

        return $value;
    }

    private function normalizeMonthName(string $value): string
    {
        return strtr($value, [
            'Januari' => 'January',
            'Februari' => 'February',
            'Maret' => 'March',
            'Mei' => 'May',
            'Juni' => 'June',
            'Juli' => 'July',
            'Agustus' => 'August',
            'Oktober' => 'October',
            'Desember' => 'December',
            'Jan' => 'Jan',
            'Feb' => 'Feb',
            'Mar' => 'Mar',
            'Apr' => 'Apr',
            'Agu' => 'Aug',
            'Okt' => 'Oct',
            'Des' => 'Dec',
        ]);
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }
}
