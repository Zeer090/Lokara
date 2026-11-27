import React from 'react';
import { useParams, Link } from 'react-router-dom';

// Mock rooms data – in real app fetch by building id
const roomsByBuilding = {
  1: [
    { id: 101, name: 'Ruang 101', capacity: 30 },
    { id: 102, name: 'Ruang 102', capacity: 20 },
  ],
  2: [
    { id: 201, name: 'Ruang 201', capacity: 40 },
    { id: 202, name: 'Ruang 202', capacity: 25 },
  ],
  3: [
    { id: 301, name: 'Ruang 301', capacity: 50 },
  ],
};

const BuildingDetail = () => {
  const { buildingId } = useParams();
  const rooms = roomsByBuilding[buildingId] || [];

  return (
    <div className="max-w-4xl mx-auto p-6">
      <div className="bg-white rounded-lg shadow p-6">
        <h1 className="text-3xl font-bold text-gray-800 mb-4">Ruangan di Gedung {buildingId}</h1>
        <ul className="space-y-3">
          {rooms.map((room) => (
            <li key={room.id} className="border-b pb-2 last:border-b-0 flex justify-between items-center">
              <div>
                <h2 className="text-xl font-semibold text-gray-700">{room.name}</h2>
                <p className="text-sm text-gray-600">Kapasitas: {room.capacity} orang</p>
              </div>
              <Link
                to={`/app/gedung/${buildingId}/ruang/${room.id}`}
                className="text-blue-600 hover:underline"
              >
                Pesan
              </Link>
            </li>
          ))}
        </ul>
        <Link to="/app/gedung" className="mt-4 inline-block text-gray-500 hover:underline">
          ← Kembali ke Daftar Gedung
        </Link>
      </div>
    </div>
  );
};

export default BuildingDetail;

