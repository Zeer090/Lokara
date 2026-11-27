import React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

const fetchLocations = async () => {
  const { data } = await api.get('/lokasi');
  return data;
};

const Home = () => {
  const { data, isLoading, error } = useQuery(['lokasi'], fetchLocations);

  if (isLoading) return <div className="flex items-center justify-center h-64"><span className="text-gray-600">Loading...</span></div>;
  if (error) return <div className="p-4 text-red-500 bg-red-50 rounded">Error loading locations.</div>;

  return (
    <div className="max-w-4xl mx-auto p-6">
      <div className="bg-white rounded-lg shadow p-6">
        <h1 className="text-3xl font-bold mb-4 text-gray-800">Daftar Lokasi</h1>
        <ul className="space-y-2">
          {data.map((loc) => (
            <li key={loc.id} className="border-b pb-2 last:border-b-0">
              <span className="font-medium text-gray-700">{loc.nama}</span> – <span className="text-gray-600">{loc.alamat}</span>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
};

export default Home;
