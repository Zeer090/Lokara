import React from 'react';
import { Link } from 'react-router-dom';

// Mock data – replace with API call later
const buildings = [
  { id: 1, name: 'Gedung A', description: 'Gedung utama kampus' },
  { id: 2, name: 'Gedung B', description: 'Gedung fakultas teknik' },
  { id: 3, name: 'Gedung C', description: 'Gedung olahraga' },
];

const BuildingList = () => (
  <div className="max-w-4xl mx-auto p-6">
    <div className="bg-white rounded-lg shadow p-6">
      <h1 className="text-3xl font-bold text-gray-800 mb-6">Daftar Gedung</h1>
      <ul className="space-y-4">
        {buildings.map((b) => (
          <li key={b.id} className="border-b pb-4 last:border-b-0">
            <Link
              to={`/app/gedung/${b.id}`}
              className="block hover:bg-gray-50 rounded p-2"
            >
              <h2 className="text-xl font-semibold text-gray-700">{b.name}</h2>
              <p className="text-gray-600">{b.description}</p>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  </div>
);

export default BuildingList;

