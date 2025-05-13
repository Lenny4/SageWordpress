interface MetadataInterface {
  id: number;
  key: string;
  value: string;
}

export const getSageMetadata = (key: string, object: MetadataInterface[]) => {
  let value = object.find((o) => o.key === "_sage_" + key);
  if (value) {
    try {
      return JSON.parse(value.value);
    } catch (e) {
      // nothing
    }
    return value.value;
  }
  return null;
};
