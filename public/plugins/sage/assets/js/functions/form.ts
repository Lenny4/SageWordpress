const notEmptyValidator = (
  value: string | number | undefined | null | any[],
) => {
  if (typeof value === "string") {
    value = value?.replace(" ", "") ?? "";
  }
  if (Array.isArray(value) && value.length === 0) {
    return "error.not_empty";
  }
  if (!value) {
    return "error.not_empty";
  }
  return "";
};
export default notEmptyValidator;
