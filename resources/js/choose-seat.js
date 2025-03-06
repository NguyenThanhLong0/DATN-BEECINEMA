import './bootstrap';

// const showtimeId = document.getElementById('showtime-id').value;

// window.Echo.channel(`showtime.${showtimeId}`)
//     .listen('SeatHold', (e) => {
//         const seatElement = document.getElementById(`seat-${e.seatId}`);
//         if (seatElement && !seatElement.classList.contains('selected')) {
//             seatElement.classList.add('hold');
//             seatElement.classList.remove('available');
//         }
//     })
//     .listen('SeatRelease', (e) => {
//         const seatElement = document.getElementById(`seat-${e.seatId}`);
//         if (seatElement) {
//             // if (seatElement.classList.contains('selected')) {
//             //     const seatLabel = seatElement.querySelector('.seat-label').textContent;
//             //     const seatId = seatElement.getAttribute('data-seat-id');
//             //     const seatPrice = parseInt(seatElement.getAttribute('data-seat-price'));

//             //     selectedSeats = selectedSeats.filter(s => s !== seatLabel);
//             //     selectedSeatIds = selectedSeatIds.filter(id => id !== seatId);
//             //     totalPrice -= seatPrice;

//             //     selectedSeatsDisplay.textContent = selectedSeats.join(', ');
//             //     hiddenSelectedSeats.value = selectedSeats.join(',');
//             //     hiddenSeatIds.value = selectedSeatIds.join(',');
//             //     totalPriceElement.textContent = totalPrice.toLocaleString() + ' Vnđ';
//             //     hiddenTotalPrice.value = totalPrice;

//             //     seatElement.classList.remove('selected');
//             // }

//             seatElement.classList.remove('hold');
//             seatElement.classList.add('available');
//         }
//     })
//     .listen('SeatSold', (e) => {
//         const seatElement = document.getElementById(`seat-${e.seatId}`);
//         if (seatElement && !seatElement.classList.contains('selected')) {
//             seatElement.classList.add('sold');
//             seatElement.classList.remove('hold');
//         }
//     });

const showtimeId = document.getElementById('showtime-id').value;

window.Echo.channel(`showtime.${showtimeId}`)
    .listen('SeatStatusChange', (e) => {
        const seatElement = document.getElementById(`seat-${e.seatId}`);
        if (seatElement) {
            seatElement.classList.remove('hold', 'available', 'sold');

            if (e.status === 'hold' && !seatElement.classList.contains('selected')) {
                seatElement.classList.add('hold');
            } else if (e.status === 'available') {
                seatElement.classList.add('available');
            } else if (e.status === 'sold' && !seatElement.classList.contains('selected')) {
                seatElement.classList.add('sold');
            }
        }
    });


