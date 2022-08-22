import app from 'flarum/app';
import SettingsPage from './components/SettingsPage';

import config from '../config';

app.initializers.add(`hamzone/${config.module.name}`, () => {
    app.extensionData.for(config.module.name).registerPage(SettingsPage);
});
