import * as React from "react";
import { IconButton, Tooltip } from "@mui/material";
import InfoIcon from "@mui/icons-material/Info";
import { getTranslations } from "../../../../../functions/translations";
import { InputInterface } from "../../../../../interface/InputInterface";
import { handleChangeInputGeneric } from "../../../../../functions/form";

let translations: any = getTranslations();

export type ArPrixVenInputState = {
  defaultValue: number;
  arCoef: number | string;
  arPrixAch: number | string;
};

type FormState = {
  arPrixVen: InputInterface;
  realArPrixVen: InputInterface;
  valueLock: InputInterface;
};

export const ArPrixVenInput = React.forwardRef(
  ({ defaultValue, arCoef, arPrixAch }: ArPrixVenInputState, ref) => {
    arCoef = Number(arCoef);
    arPrixAch = Number(arPrixAch);
    const getExpectedArPrixVen = () => {
      return Number((arCoef * arPrixAch).toFixed(2));
    };
    const [expectedArPrixVen, setExpectedArPrixVen] = React.useState(
      getExpectedArPrixVen(),
    );

    const getDefaultValue = (): FormState => {
      const v = defaultValue ?? 0;
      return {
        arPrixVen: { value: v.toString() },
        realArPrixVen: { value: v.toString() },
        valueLock: { value: v > 0 },
      };
    };
    const [values, setValues] = React.useState<FormState>(getDefaultValue());

    const handleChange =
      (prop: keyof FormState) =>
      (event: React.ChangeEvent<HTMLInputElement>) => {
        setValues((v) => {
          return {
            ...v,
            valueLock: {
              ...v.valueLock,
              value: true,
            },
          };
        });
        handleChangeInputGeneric(event, prop, setValues);
      };
    const handleRealArPrixVen = () => {
      setValues((v) => {
        return {
          ...v,
          realArPrixVen: {
            ...v.realArPrixVen,
            value:
              v.valueLock.value &&
              expectedArPrixVen !== Number(v.arPrixVen.value)
                ? v.arPrixVen.value
                : "0",
          },
        };
      });
    };

    const resetArPrixVen = () => {
      const newValue = getExpectedArPrixVen();
      setValues((v) => {
        return {
          ...v,
          arPrixVen: {
            ...v.arPrixVen,
            value: newValue.toString(),
          },
          valueLock: {
            ...v.valueLock,
            value: false,
          },
        };
      });
      setExpectedArPrixVen(newValue);
    };

    React.useEffect(() => {
      handleRealArPrixVen();
    }, [expectedArPrixVen, values.arPrixVen.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      resetArPrixVen();
    }, [arCoef, arPrixAch]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
      <>
        <label htmlFor={"_sage_arPrixVen"}>
          <Tooltip title={"arPrixVen"} arrow placement="top">
            <span>{translations["fArticles"]["arPrixVen"]}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "flex-start" }}>
          <div style={{ position: "relative", flex: 1 }}>
            <input
              id={"_sage_arPrixVen"}
              name={"_sage_arPrixVen"}
              type={"hidden"}
              value={values.realArPrixVen.value}
            />
            <input
              type={"number"}
              value={values.arPrixVen.value}
              onChange={handleChange("arPrixVen")}
              style={{ width: "100%" }}
              onBlur={() => {
                if (Number(values.arPrixVen.value) === 0) {
                  resetArPrixVen();
                }
              }}
            />
            {values.arPrixVen.error && (
              <div className="sage_error_field">{values.arPrixVen.error}</div>
            )}
          </div>
          {Number(values.arPrixVen.value) !== expectedArPrixVen &&
            Number(values.arPrixVen.value) > 0 && (
              <div style={{ position: "relative", top: "-2px" }}>
                <Tooltip title={translations.sentences.acPrixVenInput} arrow>
                  <IconButton>
                    <InfoIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
              </div>
            )}
        </div>
      </>
    );
  },
);
