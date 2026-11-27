import React from 'react';

// Mock bookings data – replace with API later
const bookings = [
  { id: 1, building: 'Gedung A', room: 'Ruang 101', date: '2024-10-01', status: 'Dikonfirmasi' },
  { id: 2, building: 'Gedung B', room: 'Ruang 202', date: '2024-10-05', status: 'Menunggu' },
];

const BookingList = () => (
  <div className="max-w-4xl mx-auto p-6">
    <div className="bg-white rounded-lg shadow p-6">
      <h1 className="text-3xl font-bold text-gray-800 mb-4">Pemesananku</h1>
      <ul className="space-y-3">
        {bookings.map((b) => (
          <li key={b.id} className="border-b pb-2 last:border-b-0 flex justify-between items-center">
            <div>
              <p className="font-medium text-gray-700">{b.building} – {b.room}</p>
              <p className="text-sm text-gray-600">{b.date}</p>
            </div>
            <span className="text-sm font-medium text-blue-600">{b.status}</span>
          </li>
        ))}
      </ul>
    </div>
  </div>
);

export default BookingList;

