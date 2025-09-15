import * as React from "react";
import { useImperativeHandle, useRef } from "react";
import { Tooltip } from "@mui/material";
import {
  FieldInterface,
  FormValidInterface,
} from "../../../../interface/InputInterface";
import {
  handleChangeCheckboxGeneric,
  isValidGeneric,
} from "../../../../functions/form";
import {
  CannotBeChangeOnWebsiteComponent,
  FieldTooltipComponent,
} from "./FormFieldComponent";
import { TOKEN } from "../../../../token";

export const FormCheckbox = React.forwardRef(
  (
    {
      label,
      name,
      readOnly,
      hideLabel,
      errorMessage,
      cannotBeChangeOnWebsite,
      tooltip,
      initValues,
      triggerFormContentChanged,
    }: FieldInterface,
    ref,
  ) => {
    const inputRef = useRef<any>(null);
    const nameField = `_${TOKEN}_` + name;
    const [values, setValues] = React.useState({
      [name]: {
        ...initValues,
        value: !!initValues.value, // Convert to boolean
      },
    });

    const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeCheckboxGeneric(event, name, setValues);
    };

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<FormValidInterface> {
        const valid = await isValidGeneric(values, setValues);
        return {
          valid: valid,
          details: [
            {
              valid: valid,
              ref: ref,
              dRef: inputRef,
            },
          ],
        };
      },
    }));

    React.useEffect(() => {
      if (triggerFormContentChanged) {
        triggerFormContentChanged(name, values[name].value.toString());
      }
    }, [values[name].value]);

    const checked = values[name].value;
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
            type="hidden"
            id={nameField}
            name={nameField}
            value={checked ? "1" : "0"}
          />
          <input
            type="checkbox"
            value="1"
            checked={checked}
            readOnly={readOnly || values[name].readOnly}
            onChange={handleChange}
          />
          <label
            htmlFor={nameField}
            style={{
              display: hideLabel ? "none" : "auto",
            }}
          >
            <Tooltip title={name} arrow placement="top">
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
        {errorMessage && (
          <div className={`${TOKEN}_error_field`}>{errorMessage}</div>
        )}
        {values[name].error && (
          <div className={`${TOKEN}_error_field`}>{values[name].error}</div>
        )}
      </>
    );
  },
);
