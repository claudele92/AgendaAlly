import { setOptions, importLibrary } from '@googlemaps/js-api-loader';
import { useEffect, useState } from 'react';
import getMapApiKey from './getMapApiKey';

// Every library the app actually uses google.maps.* classes from, across
// both the direct google-maps-react map components (LatLngBounds, Size —
// part of 'maps') and the react-google-autocomplete-based address inputs
// (places.AutocompleteService, places.Autocomplete, places.PlacesService —
// part of 'places'). 'geometry' matches what the map components previously
// requested from google-maps-react's own loader.
const REQUIRED_LIBRARIES = ['maps', 'places', 'geometry'];

let readyPromise = null;

// Single shared script load for the whole app. Previously, google-maps-react
// (used by the map components) and react-google-autocomplete (used by every
// address-autocomplete input) each ran their own independent script-loading
// logic with no knowledge of each other. Whichever mounted second would find
// the other's <script> tag already in the DOM and fall back to waiting on
// that tag's native `load` event — but Google's Maps JS API fires `load`
// once its small bootstrap payload finishes executing, *before* requested
// sub-libraries like `places` are actually fetched and attached to
// `window.google.maps`. That race is what let a genuinely-loading page
// reference `google.maps.places.AutocompleteService()` before `google` was
// even defined as a global, crashing the whole page.
//
// google.maps.importLibrary (Google's own recommended "dynamic library
// import" pattern, loaded with the modern `loading=async` script attribute)
// is idempotent and promise-based, so calling this from as many components
// as need it is safe — they all await the same in-flight load.
function getGoogleMapsReadyPromise() {
  if (!readyPromise) {
    setOptions({ key: getMapApiKey(), v: 'weekly' });
    readyPromise = Promise.all(
      REQUIRED_LIBRARIES.map((library) => importLibrary(library)),
    );
  }

  return readyPromise;
}

export default function useGoogleMapsReady() {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let mounted = true;

    getGoogleMapsReadyPromise()
      .then(() => {
        if (mounted) {
          setReady(true);
        }
      })
      .catch((error) => {
        console.error('Failed to load the Google Maps JavaScript API', error);
      });

    return () => {
      mounted = false;
    };
  }, []);

  return ready;
}
