import {TOKEN} from "../token";

interface MetadataInterface {
  id: number;
  key: string;
  value: string;
}

export const getSageMetadata = (
  key: string,
  object: MetadataInterface[] | null,
  defaultValue: any = "",
) => {
  if (object == null) {
    return null;
  }
  let value = object.find((o) => o.key === `_${TOKEN}_` + key);
  if (value) {
    try {
      return JSON.parse(value.value) ?? defaultValue;
    } catch (e) {
      // nothing
    }
    return value.value ?? defaultValue;
  }
  return defaultValue ?? null;
};

export const getListObjectSageMetadata = (
  prefix: string,
  object: MetadataInterface[] | null,
  asArrayId: string = null,
): any => {
  if (object == null) {
    return null;
  }
  prefix = `_${TOKEN}_` + prefix;
  const result: any = {};
  const regex = new RegExp(
    `^${prefix.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}\\[(.+?)\\]\\.([^.]+)$`,
  );
  for (const key in object) {
    if (object[key].key.startsWith(prefix)) {
      const match = object[key].key.match(regex);
      if (match) {
        const identifier = match[1];
        const prop = match[2];

        if (!result[identifier]) {
          result[identifier] = {};
        }

        result[identifier][prop] = object[key].value;
      }
    }
  }
  if (asArrayId) {
    return Object.keys(result).map((key) => {
      return {
        [asArrayId]: key,
        ...result[key],
      };
    });
  }
  return result;
};
