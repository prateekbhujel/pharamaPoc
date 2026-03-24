import '../css/app.css';
import './bootstrap';

import { createRoot } from 'react-dom/client';
import App from './spa/App';

createRoot(document.getElementById('app')).render(<App />);
