<?php

namespace App\Jobs;

use App\Models\Room;
use App\Models\SeatShowtime;
use App\Models\Showtime;
use App\Services\PriceCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateSeatShowtimesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $showtime;
    protected $room;
    protected $priceCalculationService;

    /**
     * Create a new job instance.
     *
     * @param Showtime $showtime
     * @param Room $room
     * @param PriceCalculationService $priceCalculationService
     */
    public function __construct(Showtime $showtime, Room $room, PriceCalculationService $priceCalculationService)
    {
        $this->showtime = $showtime;
        $this->room = $room;
        $this->priceCalculationService = $priceCalculationService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Log showtime and room details
            // Log::info('Processing CreateSeatShowtimesJob', [
            //     'showtime_id' => $this->showtime->id,
            //     'showtime_data' => [
            //         'movie_id' => $this->showtime->movie_id,
            //         'movie_version_id' => $this->showtime->movie_version_id,
            //         'room_id' => $this->showtime->room_id,
            //         'cinema_id' => $this->showtime->cinema_id,
            //         'date' => $this->showtime->date,
            //         'start_time' => $this->showtime->start_time,
            //         'end_time' => $this->showtime->end_time,
            //         'format' => $this->showtime->format,
            //     ],
            //     'room_id' => $this->room->id,
            //     'room_data' => [
            //         'cinema_id' => $this->room->cinema_id,
            //         'type_room_id' => $this->room->type_room_id,
            //         'type_room_name' => optional($this->room->typeRoom)->name,
            //     ],
            // ]);

            $seatShowtimesToInsert = [];

            // Load seats for the room
            $seats = $this->room->seats()->select('id' , 'type_seat_id')->get();
            

            if ($seats->isEmpty()) {
                // Log::warning('No seats found for room', [
                //     'room_id' => $this->room->id,
                //     'showtime_id' => $this->showtime->id,
                // ]);
                return;
            }

            foreach ($seats as $seat) {
                // Log::info('Chi tiết seat:', $seat->toArray());

                $price = $this->priceCalculationService->calculatePrice($this->showtime, $seat);

                // Log price calculation result
                // Log::info('Calculated price for seat', [
                //     'showtime_id' => $this->showtime->id,
                //     'seat_id' => $seat->id,
                //     'price' => $price,
                // ]);

                if ($price <= 0) {
                    Log::warning('Invalid price calculated', [
                        'showtime_id' => $this->showtime->id,
                        'seat_id' => $seat->id,
                        'price' => $price,
                    ]);
                }

                $seatShowtimesToInsert[] = [
                    'showtime_id' => $this->showtime->id,
                    'seat_id' => $seat->id,
                    'status' => 'available',
                    'price' => $price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Chunk insert SeatShowtime to avoid memory issues
            collect($seatShowtimesToInsert)->chunk(1000)->each(function ($chunk) {
                SeatShowtime::insert($chunk->toArray());
            });

            // Log::info('Successfully created SeatShowtimes', [
            //     'showtime_id' => $this->showtime->id,
            //     'seat_count' => count($seatShowtimesToInsert),
            // ]);
        } catch (\Throwable $e) {
            // Log error for debugging
            // Log::error('Failed to create SeatShowtime for showtime ' . $this->showtime->id, [
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString(),
            // ]);
            throw $e; // Re-throw to allow queue retry or failure handling
        }
    }
}