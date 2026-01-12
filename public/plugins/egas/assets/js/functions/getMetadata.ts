import { TOKEN } from "../token";

interface MetadataInterface {
  id: number;
  key: string;
  value: string;
}

const isValidNumber = (t: string | number) => {
  return typeof t === "number" && !Number.isNaN(t);
};

export const toBoolean = (
  v: string | number | null | undefined | boolean,
): boolean => {
  if (v === null || v === undefined) return false;

  if (typeof v === "boolean") return v;

  if (typeof v === "number") return v !== 0;

  if (typeof v === "string") {
    const value = v.trim().toLowerCase();
    return value === "true" || value === "1" || value === "yes";
  }

  return false;
};

const _useDefaultIfZero = (
  v1: string | number,
  v2: string | number,
  useDefaultIfZero: boolean,
) => {
  if (isValidNumber(v1) && useDefaultIfZero && Number(v1) === 0) {
    return v2;
  }
  return v1 ?? v2;
};

export const getSageMetadata = (
  key: string,
  object: MetadataInterface[] | null,
  defaultValue: any = "",
  useDefaultIfZero: boolean = false,
) => {
  if (object == null) {
    return null;
  }
  let value = object.find((o) => o.key === `_${TOKEN}_` + key);
  if (value) {
    try {
      return _useDefaultIfZero(
        JSON.parse(value.value),
        defaultValue,
        useDefaultIfZero,
      );
    } catch (e) {
      // nothing
    }
    return _useDefaultIfZero(value.value, defaultValue, useDefaultIfZero);
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
