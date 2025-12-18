import * as React from "react";
import { useImperativeHandle, useRef } from "react";
import { Tooltip } from "@mui/material";
import {
  FieldInterface,
  FormValidInterface,
} from "../../../../interface/InputInterface";
import {
  handleChangeSelectGeneric,
  isValidGeneric,
} from "../../../../functions/form";
import {
  CannotBeChangeOnWebsiteComponent,
  FieldTooltipComponent,
} from "./FormFieldComponent";
import { TOKEN } from "../../../../token";

export const FormSelect = React.forwardRef(
  (
    {
      label,
      name,
      readOnly,
      hideLabel,
      options = [],
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
        value: initValues.value.toString(),
      },
    });

    const handleChangeSelect = (
      event: React.ChangeEvent<HTMLSelectElement>,
    ) => {
      handleChangeSelectGeneric(event, name, setValues);
    };
    const hasOption = !!options.find(
      (o) => o.value.toString() === values[name].value.toString(),
    );

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
        triggerFormContentChanged(name, values[name].value);
      }
    }, [values[name].value]);

    const thisReadOnly = readOnly || values[name].readOnly;
    return (
      <>
        <label
          htmlFor={nameField}
          style={{
            display: hideLabel ? "none" : "block",
          }}
        >
          <Tooltip title={name} arrow placement="top">
            <span>{label}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex" }}>
          <div style={{ flex: 1 }}>
            <select
              id={nameField}
              name={nameField}
              value={values[name].value}
              onChange={handleChangeSelect}
              className={thisReadOnly ? "grayed-out-select" : ""}
              style={{ width: "100%" }}
              ref={inputRef}
            >
              {options.map((opt, index) => {
                return (
                  <option
                    disabled={
                      opt.disabled ||
                      (thisReadOnly &&
                        !(
                          opt.value.toString() ===
                            values[name].value.toString() ||
                          (!hasOption && index === 0)
                        ))
                    }
                    key={opt.value}
                    value={opt.value}
                  >
                    {opt.label}
                  </option>
                );
              })}
            </select>
          </div>
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
