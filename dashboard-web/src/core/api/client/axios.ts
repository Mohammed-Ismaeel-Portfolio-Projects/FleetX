import axios from 'axios';

/**
 * Axios Client Configuration
 * 
 * This is the configured Axios instance for all API requests.
 * 
 * TODO: Update baseURL when Laravel backend is ready
 * TODO: Configure timeout, retries, and other axios settings
 * TODO: Add environment-based configuration
 */

const axiosClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

export default axiosClient;
