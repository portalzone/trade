import Link from 'next/link';

export default function Home() {
  return (
    <div className="min-h-screen bg-gradient-to-r from-blue-600 to-blue-800">
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center text-white p-8">
          <h1 className="text-6xl font-bold mb-4">T-Trade</h1>
          <p className="text-2xl mb-8">Secure Peer-to-Peer Marketplace</p>
          <p className="text-lg mb-8">Buy and sell with confidence using escrow protection</p>
          <div className="flex gap-4 justify-center">
            <Link
              href="/marketplace"
              className="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition"
            >
              Browse Marketplace
            </Link>
            <Link
              href="/login"
              className="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition"
            >
              Login
            </Link>
            <Link
              href="/register"
              className="bg-blue-700 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-800 transition"
            >
              Sign Up
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
