import * as React from "react";
import { FieldInterface } from "../../../interface/InputInterface";
import { Tooltip } from "@mui/material";
import {
  CannotBeChangeOnWebsiteComponent,
  FieldTooltipComponent,
} from "./FormFieldComponent";

export const FormCheckbox: React.FC<FieldInterface> = ({
  label,
  name,
  readOnly,
  hideLabel,
  errorMessage,
  cannotBeChangeOnWebsite,
  tooltip,
  initValues,
  triggerFormContentChanged,
}) => {
  const [values, setValues] = React.useState({
    ...initValues,
    value: !!initValues.value, // Convert to boolean
  });

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const isChecked = event.target.checked;

    setValues((v) => ({
      ...v,
      value: isChecked,
      error: "",
    }));
  };

  React.useEffect(() => {
    if (triggerFormContentChanged) {
      triggerFormContentChanged(name, values.value.toString());
    }
  }, [values.value]);

  name = "_sage_" + name;

  return (
    <>
      <div
        style={{
          display: "flex",
          alignItems: "center",
          gap: "0.5rem",
          marginBottom: "0.5rem",
        }}
      >
        <input
          id={name}
          name={name}
          type="checkbox"
          checked={values.value}
          readOnly={readOnly || values.readOnly}
          onChange={handleChange}
        />
        <label
          htmlFor={name}
          style={{
            display: hideLabel ? "none" : "auto",
          }}
        >
          <Tooltip title={name.replace("_sage_", "")} arrow placement="top">
            <span>{label}</span>
          </Tooltip>
        </label>
      </div>

      <div style={{ display: "flex", alignItems: "center" }}>
        <CannotBeChangeOnWebsiteComponent
          cannotBeChangeOnWebsite={cannotBeChangeOnWebsite}
        />
        <FieldTooltipComponent tooltip={tooltip} />
      </div>

      {errorMessage && <div className="sage_error_field">{errorMessage}</div>}
    </>
  );
};
