interface MetadataInterface {
  id: number;
  key: string;
  value: string;
}

export const getSageMetadata = (
  key: string,
  object: MetadataInterface[] | null,
  asArray: boolean = false,
) => {
  if (object == null) {
    return null;
  }
  let value = object.find((o) => o.key === "_sage_" + key);
  if (value) {
    try {
      const result = JSON.parse(value.value);
      if (asArray) {
        return Object.keys(result).map((key) => result[key]);
      }
      return result;
    } catch (e) {
      // nothing
    }
    return value.value;
  }
  return null;
};
